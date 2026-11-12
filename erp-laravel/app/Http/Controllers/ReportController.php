<?php

namespace App\Http\Controllers;

use App\Support\ErpModules;
use App\Support\ErpViewData;
use App\Support\ReportCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        [$catalog, $dataset] = $this->resolveDataset($request);
        $query = $this->buildQuery($request, $dataset);
        $summary = $this->summary(clone $query, $dataset);
        $groupBy = $this->groupBy($request, $dataset);
        $chartRows = $this->chartRows(clone $query, $dataset, $groupBy);
        $rows = $this->applySorting($request, $query, $dataset)
            ->select(array_keys($dataset['columns']))
            ->paginate(25)
            ->withQueryString();

        return view('reports.index', array_merge(ErpViewData::layout(), [
            'catalog' => $catalog,
            'dataset' => $dataset,
            'rows' => $rows,
            'summary' => $summary,
            'chartRows' => $chartRows,
            'groupBy' => $groupBy,
            'filters' => $request->all(),
            'allModules' => ErpModules::all(),
        ]));
    }

    public function export(Request $request): StreamedResponse
    {
        [, $dataset] = $this->resolveDataset($request);
        $query = $this->applySorting($request, $this->buildQuery($request, $dataset), $dataset)
            ->select(array_keys($dataset['columns']))
            ->limit(5000);

        $filename = 'report-'.str_replace([':', ' '], '-', $dataset['key']).'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($query, $dataset) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, array_values($dataset['columns']));

            $query->chunk(500, function ($rows) use ($handle, $dataset) {
                foreach ($rows as $row) {
                    fputcsv($handle, collect(array_keys($dataset['columns']))
                        ->map(fn ($column) => $this->formatCell($row->{$column} ?? null, $column, false))
                        ->all());
                }
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function resolveDataset(Request $request): array
    {
        $catalog = ReportCatalog::forUser($request->user());

        abort_if(empty($catalog), 403, 'Belum ada report yang bisa diakses.');

        $key = $request->get('dataset', array_key_first($catalog));

        abort_unless(isset($catalog[$key]), 404, 'Dataset report tidak tersedia.');

        return [$catalog, $catalog[$key]];
    }

    private function buildQuery(Request $request, array $dataset)
    {
        $query = DB::table($dataset['table']);

        foreach (($dataset['base'] ?? []) as $column => $value) {
            $query->where($column, $value);
        }

        if (($dataset['table'] ?? null) === 'erp_records') {
            $allowedModules = $dataset['allowed_modules'] ?? [];
            $query->whereIn('module', $allowedModules);

            if ($request->filled('module') && in_array($request->module, $allowedModules, true)) {
                $query->where('module', $request->module);
            }
        }

        if ($request->filled('q')) {
            $searchable = $dataset['searchable'] ?? [];
            $search = $request->q;
            $query->where(function ($inner) use ($searchable, $search) {
                foreach ($searchable as $column) {
                    $inner->orWhere($column, 'like', "%{$search}%");
                }
            });
        }

        if ($request->filled('status') && isset($dataset['status_column'])) {
            $query->where($dataset['status_column'], $request->status);
        }

        $dateColumn = $this->dateColumn($request, $dataset);

        if ($dateColumn && $request->filled('date_from')) {
            $query->whereDate($dateColumn, '>=', $request->date_from);
        }

        if ($dateColumn && $request->filled('date_to')) {
            $query->whereDate($dateColumn, '<=', $request->date_to);
        }

        if (isset($dataset['amount_column']) && $request->filled('min_amount')) {
            $query->where($dataset['amount_column'], '>=', (float) $request->min_amount);
        }

        if (isset($dataset['amount_column']) && $request->filled('max_amount')) {
            $query->where($dataset['amount_column'], '<=', (float) $request->max_amount);
        }

        return $query;
    }

    private function summary($query, array $dataset): array
    {
        $amountColumn = $dataset['amount_column'] ?? null;

        return [
            'total_rows' => (clone $query)->count(),
            'total_amount' => $amountColumn ? (clone $query)->sum($amountColumn) : null,
            'avg_amount' => $amountColumn ? (clone $query)->avg($amountColumn) : null,
            'max_amount' => $amountColumn ? (clone $query)->max($amountColumn) : null,
        ];
    }

    private function chartRows($query, array $dataset, ?string $groupBy)
    {
        if (! $groupBy) {
            return collect();
        }

        $amountColumn = $dataset['amount_column'] ?? null;
        $select = "{$groupBy} as label, count(*) as total";

        if ($amountColumn) {
            $select .= ", sum({$amountColumn}) as amount";
        }

        return $query
            ->selectRaw($select)
            ->groupBy($groupBy)
            ->orderByDesc($amountColumn ? 'amount' : 'total')
            ->limit(10)
            ->get()
            ->map(function ($row) {
                $row->label = $row->label ?: '-';

                return $row;
            });
    }

    private function applySorting(Request $request, $query, array $dataset)
    {
        $columns = array_keys($dataset['columns']);
        $sort = in_array($request->get('sort'), $columns, true)
            ? $request->get('sort')
            : ($dataset['default_sort'] ?? $columns[0]);
        $direction = $request->get('direction') === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sort, $direction);
    }

    private function groupBy(Request $request, array $dataset): ?string
    {
        $groupable = array_keys($dataset['groupable'] ?? []);
        $groupBy = $request->get('group_by', $dataset['default_group'] ?? null);

        return in_array($groupBy, $groupable, true) ? $groupBy : null;
    }

    private function dateColumn(Request $request, array $dataset): ?string
    {
        $dateColumns = array_keys($dataset['date_columns'] ?? []);
        $dateColumn = $request->get('date_column', $dateColumns[0] ?? null);

        return in_array($dateColumn, $dateColumns, true) ? $dateColumn : null;
    }

    private function formatCell($value, string $column, bool $forHtml = true): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        if ($column === 'amount' || str_contains($column, 'salary') || in_array($column, ['allowance', 'deduction', 'reimbursement', 'customer_shipping_charge', 'courier_cost', 'insurance_cost', 'sales_price', 'standard_cost', 'unit_price', 'unit_cost', 'line_amount'], true)) {
            return 'Rp '.number_format((float) $value, 0, ',', '.');
        }

        if (str_ends_with($column, '_at') || in_array($column, ['due_date', 'attendance_date', 'period_month', 'join_date', 'start_date', 'end_date', 'expense_date'], true)) {
            return \Illuminate\Support\Carbon::parse($value)->format('d M Y H:i');
        }

        if ($column === 'progress') {
            return (int) $value.'%';
        }

        return (string) $value;
    }
}
