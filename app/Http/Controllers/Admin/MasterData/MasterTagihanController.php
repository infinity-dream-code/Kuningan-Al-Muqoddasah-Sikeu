<?php

namespace App\Http\Controllers\Admin\MasterData;

use App\Http\Controllers\Controller;
use App\Models\mst_tagihan;
use App\Models\ValidationMessage;
use App\Support\MultiVa;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class MasterTagihanController extends Controller
{
    public string $title = 'Master Data';
    public string $mainTitle = 'Master Tagihan';
    public string $dataTitle = 'Master Tagihan';

    public function index()
    {
        $data['title'] = $this->title;
        $data['mainTitle'] = $this->mainTitle;
        $data['dataTitle'] = $this->dataTitle;
        $data['columnsUrl'] = route('admin.master-data.master-tagihan.get-column');
        $data['datasUrl'] = route('admin.master-data.master-tagihan.get-data');
        $data['vaSelectable'] = MultiVa::masterVaSelectable();

        return view('admin.master_data.master_tagihan.index', $data);
    }

    public function getColumn()
    {
        $columns = [
            ['data' => null, 'name' => 'no', 'className' => 'text-center', 'columnType' => 'no'],
            ['data' => 'tagihan', 'name' => 'Nama Tagihan', 'searchable' => true, 'orderable' => true],
        ];

        if (MultiVa::masterVaSelectable()) {
            $columns[] = ['data' => 'VA', 'name' => 'VA', 'searchable' => true, 'orderable' => true];
        }

        $columns[] = ['data' => 'isINSTALLMENT_label', 'name' => 'Status Dapat Di Cicil', 'searchable' => false, 'orderable' => true];
        $columns[] = [
            'data' => 'edit',
            'name' => '',
            'dataVal' => false,
            'columnType' => 'button',
            'className' => 'text-center',
            'button' => 'modal',
            'buttonText' => 'Edit',
            'buttonClass' => 'btn btn-sm btn-info btn-edit',
            'buttonLink' => '#modal-edit',
            'buttonIcon' => 'ri-edit-line me-2',
        ];

        return $columns;
    }

    public function getData(Request $request)
    {
        $draw = $request->get('draw');
        $start = max(0, (int) $request->get('start', 0));
        $rowperpage = (int) $request->get('length', 10);

        $columnIndex_arr = $request->get('order', []);
        $columnName_arr = $request->get('columns', []);
        $order_arr = $request->get('order', []);
        $search_arr = $request->get('search', []);
        $searchValue = trim((string) ($search_arr['value'] ?? ''));

        $columnName = 'urut';
        $columnSortOrder = 'asc';
        $hasVaCol = MultiVa::masterHasVaColumn();

        if (!empty($order_arr)) {
            $columnIndex = $columnIndex_arr[0]['column'] ?? null;
            if ($columnIndex !== null && !empty($columnName_arr[$columnIndex]['data']) && $columnName_arr[$columnIndex]['data'] !== 'no') {
                $sortColumn = $columnName_arr[$columnIndex]['data'];
                $columnName = match ($sortColumn) {
                    'isINSTALLMENT_label' => 'isINSTALLMENT',
                    'VA', 'va_label' => $hasVaCol ? 'VA' : 'urut',
                    default => $sortColumn,
                };
                $columnSortOrder = $order_arr[0]['dir'] ?? 'asc';
            }
        }

        $allowedSort = ['urut', 'tagihan', 'isINSTALLMENT'];
        if ($hasVaCol) {
            $allowedSort[] = 'VA';
        }
        if (!in_array($columnName, $allowedSort, true)) {
            $columnName = 'urut';
        }

        try {
            $filtered = mst_tagihan::query()
                ->when($searchValue !== '', function ($q) use ($searchValue) {
                    $q->where('tagihan', 'like', '%' . $searchValue . '%');
                });

            $totalRecords = mst_tagihan::count();
            $totalRecordswithFilter = (clone $filtered)->count();

            if ($totalRecords === 0) {
                Log::warning('mst_tagihan kosong pada koneksi DATA_MYSQL', [
                    'database' => DB::connection('DATA_MYSQL')->getDatabaseName(),
                ]);
            }

            $query = $filtered
                ->orderBy($columnName, $columnSortOrder)
                ->skip($start);

            if ($rowperpage > 0) {
                $query->take($rowperpage);
            }

            $records = $query
                ->get()
                ->map(function ($item) use ($hasVaCol) {
                    if ($hasVaCol && MultiVa::closeEnabled()) {
                        $vaRaw = trim((string) ($item->VA ?? $item->va ?? ''));
                        $vaLabel = $vaRaw !== '' ? $vaRaw : MultiVa::OPEN;
                    } else {
                        $vaLabel = MultiVa::OPEN; // client open-only
                    }

                    return [
                        'urut' => $item->urut,
                        'item_id' => $item->urut,
                        'tagihan' => $item->tagihan,
                        'VA' => $vaLabel,
                        'isINSTALLMENT' => (int) $item->isINSTALLMENT,
                        'isINSTALLMENT_label' => (int) $item->isINSTALLMENT === 1
                            ? 'BISA DI CICIL'
                            : 'TIDAK BISA DI CICIL',
                        'edit' => true,
                    ];
                })
                ->values()
                ->all();

            return response()->json([
                'draw' => intval($draw),
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $totalRecordswithFilter,
                'data' => $records,
            ]);
        } catch (Exception $e) {
            Log::error('Gagal memuat master tagihan', [
                'database' => DB::connection('DATA_MYSQL')->getDatabaseName(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'draw' => intval($draw),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => $e->getMessage(),
                'message' => 'Gagal memuat master tagihan. Database: ' . DB::connection('DATA_MYSQL')->getDatabaseName(),
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $rules = [
            'tagihan' => ['required', 'string', 'max:100'],
            'isINSTALLMENT' => ['required', 'in:0,1'],
        ];

        if (MultiVa::masterVaSelectable()) {
            $rules['VA'] = ['required', 'in:93,94'];
        }

        $validator = Validator::make(
            $request->all(),
            $rules,
            ValidationMessage::messages(),
            ValidationMessage::attributes()
        );

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        $exists = mst_tagihan::where('tagihan', $request->tagihan)->first();
        if ($exists) {
            return response()->json(['message' => 'Nama tagihan sudah ada'], 422);
        }

        try {
            DB::connection('DATA_MYSQL')->beginTransaction();

            $nextUrut = (int) mst_tagihan::max('urut') + 1;

            $payload = [
                'urut' => $nextUrut,
                'tagihan' => strtoupper(trim($request->tagihan)),
                'kode' => null,
                'isINSTALLMENT' => (int) $request->isINSTALLMENT === 1 ? 1 : 0,
            ];

            if (MultiVa::masterHasVaColumn()) {
                $va = MultiVa::masterVaSelectable()
                    ? (MultiVa::normalize($request->VA) ?? MultiVa::OPEN)
                    : MultiVa::OPEN;
                $payload['VA'] = $va;
            }

            mst_tagihan::create($payload);

            DB::connection('DATA_MYSQL')->commit();
            mst_tagihan::flushInstallmentCache();

            return response()->json(['message' => 'Data ' . $this->mainTitle . ' telah disimpan']);
        } catch (Exception $e) {
            DB::connection('DATA_MYSQL')->rollBack();
            return response()->json(['message' => 'Data ' . $this->mainTitle . ' gagal disimpan', 'error' => $e->getMessage()], 422);
        }
    }

    public function update(Request $request, $id)
    {
        $rules = [
            'isINSTALLMENT' => ['required', 'in:0,1'],
        ];

        if (MultiVa::masterVaSelectable()) {
            $rules['VA'] = ['required', 'string', 'max:20'];
        }

        $validator = Validator::make(
            $request->all(),
            $rules,
            ValidationMessage::messages(),
            ValidationMessage::attributes()
        );

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        $row = mst_tagihan::where('urut', $id)->first();
        if (!$row) {
            return response()->json(['message' => 'Data tagihan tidak ditemukan'], 422);
        }

        try {
            DB::connection('DATA_MYSQL')->beginTransaction();

            if (MultiVa::masterHasVaColumn() && MultiVa::masterVaSelectable()) {
                $vaRaw = trim((string) $request->VA);
                $va = MultiVa::normalize($vaRaw) ?? $vaRaw;
                if ($va === '') {
                    return response()->json(['message' => 'Tipe VA tidak valid'], 422);
                }
                $row->VA = $va;
            }

            $row->isINSTALLMENT = (int) $request->isINSTALLMENT === 1 ? 1 : 0;
            $row->save();

            DB::connection('DATA_MYSQL')->commit();
            mst_tagihan::flushInstallmentCache();

            return response()->json(['message' => 'Data ' . $this->mainTitle . ' telah diubah']);
        } catch (Exception $e) {
            DB::connection('DATA_MYSQL')->rollBack();
            return response()->json(['message' => 'Data ' . $this->mainTitle . ' gagal diubah', 'error' => $e->getMessage()], 422);
        }
    }
}
