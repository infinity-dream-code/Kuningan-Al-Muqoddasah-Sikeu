<?php

namespace App\Http\Controllers\Admin\MasterData;

use App\Http\Controllers\Controller;
use App\Models\mst_kelas;
use App\Models\mst_sekolah;
use App\Models\scctcust;
use App\Models\ValidationMessage;
use App\Support\AndroidLogonFixerProcedure;
use App\Support\MultiVa;
use App\Support\FilterHandler;
use App\Support\SchoolScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DataSiswaController extends Controller
{
    private string $title = "Master Data";
    private string $mainTitle = "Data Siswa";
    private string $dataTitle = "Data Siswa";
    private ?string $unitScope = null;

    private array $allowedFilters = [
        "kelas" => "scctcust.DESC02",
        "sekolah" => "scctcust.CODE01",
        "siswa" => "scctcust.nmcust",
        "angkatan" => "scctcust.DESC04",
        "ayah" => "scctcust.GENUS",
    ];

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (Auth::check()) {
                $this->unitScope = SchoolScope::codeFromUser();
            }

            return $next($request);
        });
    }

    public function index()
    {
        $data["title"] = $this->title;
        $data["mainTitle"] = $this->mainTitle;
        $data["dataTitle"] = $this->dataTitle;
        $data["columnsUrl"] = route("admin.master-data.data-siswa.get-column");
        $data["datasUrl"] = route("admin.master-data.data-siswa.get-data");

        $data["thn_aka"] = scctcust::query()
            ->whereNotNull("DESC04")
            ->where("DESC04", "!=", "")
            ->when($this->unitScope, fn ($q) => $q->where("CODE01", $this->unitScope))
            ->select("DESC04 as thn_aka")
            ->distinct()
            ->orderBy("DESC04", "desc")
            ->get();

        $data["sekolah"] = mst_sekolah::when($this->unitScope, function ($query) {
            $query->where(function ($q) {
                $q->where("CODE01", $this->unitScope)
                    ->orWhere("DESC01", $this->unitScope);
            });
        })->get();

        $data["kelas"] = mst_kelas::dropdownQuery($this->unitScope)
            ->orderBy("unit")
            ->orderBy("jenjang")
            ->orderBy("kelas")
            ->get();

        return view("admin.master_data.data_siswa.index", $data);
    }

    public function getColumn()
    {
        $columns = [
            ["data" => "select_reset", "name" => "", "searchable" => false, "orderable" => false, "className" => "text-center", "exportable" => false],
            ["data" => null, "name" => "no", "className" => "text-center", "columnType" => "row", "exportable" => true],
            ["data" => "nocust", "name" => "NIS", "searchable" => true, "orderable" => true, "exportable" => true],
            ["data" => "va_open", "name" => MultiVa::closeEnabled() ? "VA Open" : "No. VA", "searchable" => false, "orderable" => false, "exportable" => true],
        ];

        if (MultiVa::closeEnabled()) {
            $columns[] = ["data" => "va_close", "name" => "VA Close", "searchable" => false, "orderable" => false, "exportable" => true];
        }

        return array_merge($columns, [
            ["data" => "NUM2ND", "name" => "No Pendaftaran", "searchable" => true, "orderable" => true, "exportable" => true],
            ["data" => "nmcust", "name" => "NAMA", "searchable" => true, "orderable" => true, "exportable" => true],
            ["data" => "CODE02", "name" => "Unit", "searchable" => true, "orderable" => true, "exportable" => true],
            ["data" => "DESC02", "name" => "Kelas", "searchable" => true, "orderable" => true, "exportable" => true],
            ["data" => "DESC03", "name" => "Kelompok", "searchable" => true, "orderable" => true, "exportable" => true],
            ["data" => "DESC04", "name" => "Angkatan", "searchable" => true, "orderable" => true, "exportable" => true],
            ["data" => "CODE04", "name" => "Gender", "searchable" => true, "orderable" => true, "exportable" => true],
            ["data" => "DESC05", "name" => "Alamat", "searchable" => true, "orderable" => true, "exportable" => true],
            ["data" => "GENUS", "name" => "Orang Tua", "searchable" => true, "orderable" => true, "exportable" => true],
            ["data" => "NO_WA", "name" => "No WA", "searchable" => true, "orderable" => true, "exportable" => true],
            ["data" => "STCUST", "name" => "Status (1/0)", "searchable" => true, "orderable" => true, "exportable" => true],
            [
                "data" => "edit_siswa",
                "name" => "Edit Data",
                "dataVal" => false,
                "columnType" => "button",
                "className" => "text-center",
                "button" => "modal",
                "buttonLink" => "#modal-edit-siswa",
                "buttonText" => "Edit",
                "buttonClass" => "btn btn-sm btn-info button-edit-siswa",
                "buttonIcon" => "ri-edit-line me-2",
            ],
            [
                "data" => "set_status",
                "name" => "Edit Status",
                "dataVal" => false,
                "columnType" => "button",
                "className" => "text-center",
                "button" => "modal",
                "buttonLink" => "#modal-edit-status-siswa",
                "buttonText" => "Edit Status",
                "buttonClass" => "btn btn-sm btn-warning button-set-status-siswa",
                "buttonIcon" => "ri-edit-box-line me-2",
            ],
        ]);
    }

    public function getData(Request $request)
    {
        $draw = (int) $request->get("draw");
        $start = (int) $request->get("start", 0);
        $length = (int) $request->get("length", 10);

        $columnName_arr = $request->get("columns", []);
        $search_arr = $request->get("search", []);

        $defaultColumn = "scctcust.nocust";
        $defaultOrder = "asc";
        $columnName = $defaultColumn;
        $columnSortOrder = $defaultOrder;

        $sortable = [
            "nocust", "NUM2ND", "nmcust", "CODE02", "DESC02", "DESC03",
            "DESC04", "CODE04", "DESC05", "GENUS", "NO_WA", "STCUST",
        ];

        $orderArr = $request->get("order");
        if (is_array($orderArr) && !empty($columnName_arr)) {
            $columnIndex = (int) ($orderArr[0]["column"] ?? 0);
            $columnSortOrder = $orderArr[0]["dir"] ?? $defaultOrder;
            $requestedColumn = $columnName_arr[$columnIndex]["data"] ?? null;
            if ($requestedColumn && in_array($requestedColumn, $sortable, true)) {
                $columnName = "scctcust." . $requestedColumn;
            }
        }

        $searchValue = $search_arr["value"] ?? "";

        $filters = [];
        $filter = FilterHandler::resolveFilters($request->input("filter"), $this->allowedFilters);
        if (!is_array($filter)) {
            $filter = [];
        }

        if ($this->unitScope !== null) {
            $filter = array_merge($filter, [
                "scctcust.CODE01" => $this->unitScope,
            ]);
        }

        foreach ($filter as $key => $val) {
            switch ($key) {
                case "scctcust.DESC02":
                    $val = explode("~~", $val);
                    if (count($val) === 3) {
                        $filters[] = ["scctcust.CODE02", "=", $val[0]];
                        $filters[] = ["scctcust.DESC02", "=", $val[1]];
                        $filters[] = ["scctcust.DESC03", "=", $val[2]];
                    }
                    break;
                case "scctcust.CODE02":
                    $filters[] = ["scctcust.CODE02", "=", $val];
                    break;
                case "scctcust.nmcust":
                    if (is_numeric($val)) {
                        $filters[] = ["scctcust.nocust", "like", "%{$val}%"];
                    } else {
                        $filters[] = ["scctcust.nmcust", "like", "%{$val}%"];
                    }
                    break;
                case "scctcust.GENUS":
                    $filters[] = [$key, "like", "%{$val}%"];
                    break;
                default:
                    $filters[] = [$key, "=", $val];
                    break;
            }
        }

        $whereAny = [
            "scctcust.nmcust",
            "scctcust.nocust",
            "scctcust.NUM2ND",
            "scctcust.GENUS",
            "scctcust.NO_WA",
            "scctcust.CODE04",
            "scctcust.DESC05",
        ];

        $select = [
            "scctcust.CUSTID",
            "scctcust.nocust",
            "scctcust.NUM2ND",
            "scctcust.nmcust",
            "scctcust.CODE02",
            "scctcust.DESC02",
            "scctcust.DESC03",
            "scctcust.DESC04",
            "scctcust.CODE04",
            "scctcust.DESC05",
            "scctcust.GENUS",
            "scctcust.NO_WA",
            "scctcust.STCUST",
        ];

        $baseQuery = scctcust::query()
            ->tap(fn ($q) => SchoolScope::applyGender($q, 'scctcust.CODE04'))
            ->when(!empty($filters), function ($q) use ($filters) {
                foreach ($filters as $f) {
                    $q->where($f[0], $f[1], $f[2]);
                }
            });

        $totalRecords = (clone $baseQuery)->count("CUSTID");

        $filteredQuery = (clone $baseQuery)->when($searchValue !== "", function ($q) use ($whereAny, $searchValue) {
            $sanitized = str_replace(["\\", "%", "_"], ["\\\\", "\\%", "\\_"], $searchValue);
            $q->where(function ($q2) use ($whereAny, $sanitized) {
                foreach ($whereAny as $column) {
                    $q2->orWhere($column, "like", "%{$sanitized}%");
                }
            });
        });

        $totalRecordsWithFilter = (clone $filteredQuery)->count("CUSTID");

        $openPrefix = MultiVa::openPrefix();
        $closeEnabled = MultiVa::closeEnabled();
        $closePrefix = $closeEnabled ? MultiVa::closePrefix() : null;

        $records = $filteredQuery
            ->orderBy($columnName, $columnSortOrder)
            ->skip($start)
            ->take($length)
            ->select($select)
            ->get()
            ->map(function ($item) use ($openPrefix, $closePrefix, $closeEnabled) {
                $row = $item->toArray();
                $nis = trim((string) ($item->nocust ?? ''));
                $row["item_id"] = $item->CUSTID;
                $row["select_reset"] = '<input type="checkbox" class="form-check-input reset-android-row" value="' . e((string) $item->CUSTID) . '">';
                $row["nis"] = $item->nocust;
                $hasNis = $nis !== '' && $nis !== '-';
                $row["va_open"] = $hasNis ? scctcust::formatVA($openPrefix, $nis) : '';
                if ($closeEnabled) {
                    $row["va_close"] = $hasNis ? scctcust::formatVA($closePrefix, $nis) : '';
                }
                $row["no_pendaftaran"] = $item->NUM2ND;
                $row["nama"] = $item->nmcust;
                $row["angkatan"] = $item->DESC04;
                $row["gender"] = SchoolScope::parseGender($item->CODE04) ?? '';
                $row["CODE04"] = SchoolScope::genderLabel($item->CODE04);
                $row["alamat"] = $item->DESC05;
                $row["ayah"] = $item->GENUS;
                $row["no_wa"] = $item->NO_WA;
                $row["edit_siswa"] = true;
                $row["set_status"] = true;
                unset($row["CUSTID"]);

                return $row;
            });

        return response()->json([
            "draw" => $draw,
            "recordsTotal" => $totalRecords,
            "recordsFiltered" => $totalRecordsWithFilter,
            "data" => $records,
        ]);
    }

    public function getSiswaSelect2(Request $request)
    {
        $term = trim((string) $request->get("term", ""));
        if ($term === "") {
            return response()->json([]);
        }

        $sanitized = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $term);
        $like = '%' . $sanitized . '%';

        $siswa = scctcust::query()
            ->where("STCUST", 1)
            ->tap(fn ($q) => SchoolScope::applyStudent($q))
            ->where(function ($q) use ($like) {
                $q->where("nocust", "like", $like)
                    ->orWhere("NUM2ND", "like", $like)
                    ->orWhere("nmcust", "like", $like);
            })
            ->orderBy("nmcust", "asc")
            ->limit(50)
            ->get(["CUSTID", "nocust", "NUM2ND", "nmcust", "CODE02", "DESC02", "DESC03", "DESC04"])
            ->map(function ($item) {
                $nis = trim((string) ($item->nocust ?? ''));
                $noDaftar = trim((string) ($item->NUM2ND ?? ''));
                $identifier = ($nis !== '' && $nis !== '-')
                    ? "NIS : {$nis}"
                    : (($noDaftar !== '' && $noDaftar !== '-')
                        ? "No. Daftar : {$noDaftar}"
                        : 'Tanpa NIS');

                return [
                    "id" => $item->CUSTID,
                    "text" => "{$identifier} - {$item->nmcust} | {$item->CODE02} - {$item->DESC02} - {$item->DESC03} - {$item->DESC04}",
                    "CUSTID" => $item->CUSTID,
                    "NOCUST" => $item->nocust,
                    "NUM2ND" => $item->NUM2ND,
                    "NMCUST" => $item->nmcust,
                    "CODE02" => $item->CODE02,
                    "DESC02" => $item->DESC02,
                    "DESC03" => $item->DESC03,
                    "DESC04" => $item->DESC04,
                    "GENUS" => null,
                ];
            });

        return response()->json($siswa);
    }

    public function getSiswa(Request $request)
    {
        $kelas = $request->kelas != "all" ? $request->kelas ?? null : null;
        $thn_aka = $request->angkatan != "all" ? $request->angkatan ?? null : null;
        $cariSiswa = $request->siswa;

        $nis = $nama = null;
        if (!empty($cariSiswa)) {
            if (is_numeric($cariSiswa)) {
                $nis = "%{$cariSiswa}%";
            } else {
                $nama = "%{$cariSiswa}%";
            }
        }

        if (!$nis && !$nama && !$kelas && !$thn_aka) {
            return response()->json([]);
        }

        $siswa = scctcust::query()
            ->tap(fn ($q) => SchoolScope::applyStudent($q))
            ->when($kelas, fn ($q) => $q->where("CODE03", $kelas))
            ->when($thn_aka, fn ($q) => $q->where("DESC04", $thn_aka))
            ->when($nis, fn ($q) => $q->where("nocust", "like", $nis))
            ->when($nama, fn ($q) => $q->where("nmcust", "like", $nama))
            ->orderBy("nmcust", "asc")
            ->limit(200)
            ->get(["CUSTID", "nocust", "NUM2ND", "nmcust", "CODE02", "DESC02", "DESC03", "DESC04"])
            ->map(function ($item) {
                return [
                    "id" => $item->CUSTID,
                    "nis" => $item->nocust,
                    "no_daftar" => $item->NUM2ND,
                    "nama" => $item->nmcust,
                    "unit" => $item->CODE02,
                    "kelompok" => $item->DESC02,
                    "kelas" => trim(($item->DESC02 ?? "") . " " . ($item->DESC03 ?? "")),
                    "thn_aka" => $item->DESC04,
                ];
            });

        return response()->json($siswa);
    }

    public function ResetLoginAndroid($id, Request $request)
    {
        $siswa = scctcust::where("CUSTID", $id)->first();
        if (!$siswa) {
            return response()->json(["message" => "Siswa tidak ditemukan!"], 422);
        }
        if (!$siswa->nocust || $siswa->nocust === "-") {
            return response()->json(["message" => "Siswa tidak memiliki NIS!"], 422);
        }

        try {
            DB::connection('DATA_MYSQL')->beginTransaction();
            AndroidLogonFixerProcedure::call((string) $siswa->nocust);
            DB::connection('DATA_MYSQL')->commit();

            return response()->json(["message" => "Login Android Direset!"], 200);
        } catch (\Throwable $e) {
            DB::connection('DATA_MYSQL')->rollBack();

            return response()->json(
                ["message" => "Gagal Mereset Login Android!", "error" => $e->getMessage()],
                422,
            );
        }
    }

    public function resetLoginAndroidBulk(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                "custids" => ["required", "array", "min:1"],
                "custids.*" => ["required", "string", "max:50"],
            ],
            ValidationMessage::messages(),
            ValidationMessage::attributes(),
        );

        if ($validator->fails()) {
            return response()->json(
                ["message" => $validator->errors()->first(), "errors" => $validator->errors()],
                422,
            );
        }

        $custids = collect($request->input("custids", []))
            ->map(fn ($id) => trim((string) $id))
            ->filter()
            ->unique()
            ->values();

        if ($custids->isEmpty()) {
            return response()->json(["message" => "Tidak ada siswa yang dipilih!"], 422);
        }

        $siswaList = scctcust::query()
            ->when($this->unitScope, fn ($q) => $q->where("CODE01", $this->unitScope))
            ->whereIn("CUSTID", $custids->all())
            ->get(["CUSTID", "nocust"]);

        if ($siswaList->isEmpty()) {
            return response()->json(["message" => "Data siswa tidak ditemukan!"], 422);
        }

        $processed = 0;
        try {
            DB::connection('DATA_MYSQL')->beginTransaction();
            foreach ($siswaList as $siswa) {
                $nis = trim((string) ($siswa->nocust ?? ""));
                if ($nis === "" || $nis === "-") {
                    continue;
                }
                AndroidLogonFixerProcedure::call($nis);
                $processed++;
            }
            DB::connection('DATA_MYSQL')->commit();
        } catch (\Throwable $e) {
            DB::connection('DATA_MYSQL')->rollBack();
            return response()->json(
                ["message" => "Gagal reset login android massal!", "error" => $e->getMessage()],
                422,
            );
        }

        if ($processed === 0) {
            return response()->json(["message" => "Tidak ada siswa valid untuk reset (NIS kosong)."], 422);
        }

        return response()->json(["message" => "Reset Android berhasil untuk {$processed} siswa."], 200);
    }

    public function setStatusSiswa($id, Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            ["stcust" => ["required", "in:0,1"]],
            ValidationMessage::messages(),
            ValidationMessage::attributes(),
        );

        if ($validator->fails()) {
            return response()->json(
                ["message" => $validator->errors()->first(), "errors" => $validator->errors()],
                422,
            );
        }

        $siswa = scctcust::where("CUSTID", $id)->first();
        if (!$siswa) {
            return response()->json(["message" => "Siswa tidak ditemukan!"], 422);
        }

        try {
            DB::beginTransaction();
            $siswa->update(["STCUST" => (int) $request->stcust]);
            DB::commit();

            $statusString = ((int) $request->stcust === 1 ? "Aktif" : "NonAktif");

            return response()->json(
                ["message" => "Status siswa {$siswa->nmcust} menjadi {$statusString}!"],
                200,
            );
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json(
                ["message" => "Gagal Mengubah Status Siswa!", "error" => $e->getMessage()],
                422,
            );
        }
    }

    public function update(Request $request, string $id)
    {
        $validator = Validator::make(
            $request->all(),
            [
                "ayah" => ["nullable", "string", "max:255"],
                "alamat" => ["nullable", "string", "max:255"],
                "gender" => ["required", "in:L,P"],
                "no_wa" => ["nullable", "string", "max:50"],
                "stcust" => ["nullable", "in:0,1"],
            ],
            ValidationMessage::messages(),
            ValidationMessage::attributes(),
        );

        if ($validator->fails()) {
            return response()->json(
                ["message" => $validator->errors()->first(), "errors" => $validator->errors()],
                422,
            );
        }

        $siswa = scctcust::when($this->unitScope, fn ($q) => $q->where("CODE01", $this->unitScope))
            ->where("CUSTID", $id)
            ->first();

        if (!$siswa || !SchoolScope::studentAccessible($siswa)) {
            return response()->json(["message" => "Siswa tidak ditemukan!"], 422);
        }

        try {
            DB::beginTransaction();
            $payload = [
                "GENUS" => $request->input("ayah") ?: null,
                "CODE04" => SchoolScope::parseGender($request->input("gender")) ?: $request->input("gender"),
                "DESC05" => $request->input("alamat") ?: null,
                "NO_WA" => $request->input("no_wa") ?: null,
                "STCUST" => $request->filled("stcust")
                    ? (int) $request->input("stcust")
                    : (int) ($siswa->STCUST ?? 0),
                "LastUpdate" => date("Y-m-d H:i:s"),
            ];
            $siswa->update($payload);
            DB::commit();

            return response()->json(["message" => "Data siswa berhasil diperbarui."], 200);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json(
                ["message" => "Gagal memperbarui data siswa.", "error" => $e->getMessage()],
                422,
            );
        }
    }
}
