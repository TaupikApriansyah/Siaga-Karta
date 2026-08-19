<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Migration ini sengaja dibuat recoverable/idempotent untuk MySQL.
        // MySQL melakukan implicit commit pada banyak operasi DDL. Jika sebuah migration
        // panjang gagal di tengah jalan, tabel/kolom yang sudah dibuat tidak otomatis rollback.
        // Guard di bawah memungkinkan migration dilanjutkan dengan aman pada start berikutnya.
        if (!Schema::hasTable('regions')) {
            Schema::create('regions', function (Blueprint $table) {
                $table->id();
                $table->string('code', 40)->unique();
                $table->string('short_code', 40)->index();
                $table->string('name', 120);
                $table->string('level', 20)->index(); // kota | kecamatan | kelurahan
                $table->foreignId('parent_id')->nullable()->constrained('regions')->cascadeOnUpdate()->restrictOnDelete();
                $table->unsignedSmallInteger('rt_count')->default(0);
                $table->unsignedSmallInteger('rw_count')->default(0);
                $table->decimal('centroid_latitude', 10, 7)->nullable();
                $table->decimal('centroid_longitude', 10, 7)->nullable();
                $table->string('geojson_name', 160)->nullable()->index();
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();
                $table->index(['level', 'parent_id']);
            });
        }

        // USERS: tambah kolom, FK, dan index secara terpisah supaya partial DDL bisa dilanjutkan.
        if (!Schema::hasColumn('users', 'region_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedBigInteger('region_id')->nullable()->after('role');
            });
        }
        $this->ensureForeignKey('users', 'region_id', 'regions', 'id', 'users_region_id_foreign');
        $this->ensureIndex('users', ['role', 'region_id'], 'users_role_region_id_index');

        // REPORTS: setiap kolom dibuat satu per satu dengan guard.
        $this->addReportColumn('region_id', function (Blueprint $table) {
            $table->unsignedBigInteger('region_id')->nullable()->after('citizen_id');
        });
        $this->addReportColumn('priority', function (Blueprint $table) {
            $table->string('priority', 20)->default('reguler')->after('category');
        });
        $this->addReportColumn('workflow_status', function (Blueprint $table) {
            $table->string('workflow_status', 40)->default('menunggu_kelurahan')->after('status');
        });
        $this->addReportColumn('escalation_level', function (Blueprint $table) {
            $table->string('escalation_level', 20)->default('kelurahan')->after('workflow_status');
        });
        $this->addReportColumn('description', function (Blueprint $table) {
            $table->text('description')->nullable()->after('medical_condition');
        });
        $this->addReportColumn('rt_number', function (Blueprint $table) {
            $table->string('rt_number', 10)->nullable()->after('pickup_location');
        });
        $this->addReportColumn('rw_number', function (Blueprint $table) {
            $table->string('rw_number', 10)->nullable()->after('rt_number');
        });
        $this->addReportColumn('latitude', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('rw_number');
        });
        $this->addReportColumn('longitude', function (Blueprint $table) {
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });
        $this->addReportColumn('submitted_by', function (Blueprint $table) {
            $table->unsignedBigInteger('submitted_by')->nullable()->after('handled_by');
        });
        $this->addReportColumn('kecamatan_verified_by', function (Blueprint $table) {
            $table->unsignedBigInteger('kecamatan_verified_by')->nullable()->after('verified_by');
        });
        $this->addReportColumn('kecamatan_verified_at', function (Blueprint $table) {
            $table->timestamp('kecamatan_verified_at')->nullable()->after('verified_at');
        });
        $this->addReportColumn('kota_received_at', function (Blueprint $table) {
            $table->timestamp('kota_received_at')->nullable()->after('kecamatan_verified_at');
        });
        $this->addReportColumn('assigned_agency', function (Blueprint $table) {
            $table->string('assigned_agency', 160)->nullable()->after('kota_received_at');
        });
        $this->addReportColumn('agency_referred_at', function (Blueprint $table) {
            $table->timestamp('agency_referred_at')->nullable()->after('assigned_agency');
        });

        $this->ensureForeignKey('reports', 'region_id', 'regions', 'id', 'reports_region_id_foreign');
        $this->ensureForeignKey('reports', 'submitted_by', 'users', 'id', 'reports_submitted_by_foreign');
        $this->ensureForeignKey('reports', 'kecamatan_verified_by', 'users', 'id', 'reports_kecamatan_verified_by_foreign');

        $this->ensureIndex('reports', ['priority'], 'reports_priority_index');
        $this->ensureIndex('reports', ['workflow_status'], 'reports_workflow_status_index');
        $this->ensureIndex('reports', ['escalation_level'], 'reports_escalation_level_index');
        $this->ensureIndex('reports', ['latitude'], 'reports_latitude_index');
        $this->ensureIndex('reports', ['longitude'], 'reports_longitude_index');
        $this->ensureIndex('reports', ['region_id', 'created_at'], 'reports_region_id_created_at_index');
        $this->ensureIndex('reports', ['region_id', 'workflow_status'], 'reports_region_id_workflow_status_index');
        $this->ensureIndex('reports', ['category', 'priority', 'created_at'], 'reports_category_priority_created_at_index');

        // Field operasional ambulans dibuat kondisional. Perintah change() aman dijalankan
        // ulang dan tidak bergantung pada apakah proses sebelumnya berhenti setelah DDL tertentu.
        Schema::table('reports', function (Blueprint $table) {
            $table->string('type', 20)->nullable()->change();
            $table->text('pickup_location')->nullable()->change();
            $table->text('medical_condition')->nullable()->change();
        });

        // Pilot hierarchy: Kota Bandung > Kecamatan Andir > Kelurahan Dungus Cariang.
        $now = now();
        DB::table('regions')->updateOrInsert(
            ['code' => '32.73'],
            ['short_code' => 'BANDUNG', 'name' => 'Kota Bandung', 'level' => 'kota', 'parent_id' => null,
             'rt_count' => 0, 'rw_count' => 0, 'centroid_latitude' => -6.9175, 'centroid_longitude' => 107.6191,
             'geojson_name' => 'KOTA BANDUNG', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]
        );
        $cityId = DB::table('regions')->where('code', '32.73')->value('id');

        DB::table('regions')->updateOrInsert(
            ['code' => '32.73.05'],
            ['short_code' => 'ANDIR', 'name' => 'Kecamatan Andir', 'level' => 'kecamatan', 'parent_id' => $cityId,
             'rt_count' => 0, 'rw_count' => 0, 'centroid_latitude' => -6.9088, 'centroid_longitude' => 107.5748,
             'geojson_name' => 'ANDIR', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]
        );
        $districtId = DB::table('regions')->where('code', '32.73.05')->value('id');

        DB::table('regions')->updateOrInsert(
            ['code' => '32.73.05.1002'],
            ['short_code' => 'DUNGUSCARIANG', 'name' => 'Kelurahan Dungus Cariang', 'level' => 'kelurahan', 'parent_id' => $districtId,
             'rt_count' => 11, 'rw_count' => 11, 'centroid_latitude' => -6.9125539, 'centroid_longitude' => 107.5806558,
             'geojson_name' => 'DUNGUS CARIANG', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]
        );
        $villageId = DB::table('regions')->where('code', '32.73.05.1002')->value('id');

        // Akun lama dipetakan ke pilot wilayah sesuai role. Akun Kota tetap root.
        DB::table('users')->where('role', 'kota')->whereNull('region_id')->update(['region_id' => $cityId]);
        DB::table('users')->where('role', 'kecamatan')->whereNull('region_id')->update(['region_id' => $districtId]);
        DB::table('users')->where('role', 'kelurahan')->whereNull('region_id')->update(['region_id' => $villageId]);

        // Normalisasi kategori lama tanpa menghapus laporan historis.
        DB::table('reports')->where('category', 'bencana')->update(['category' => 'kebencanaan']);

        // Kanal tetap WhatsApp. Gmail dipakai sebagai alamat notifikasi warga, bukan sumber laporan.
        DB::table('reports')->where('source', 'gmail')->update(['source' => 'whatsapp']);
    }

    public function down(): void
    {
        if (Schema::hasTable('reports')) {
            $this->dropForeignIfExists('reports', 'kecamatan_verified_by', 'reports_kecamatan_verified_by_foreign');
            $this->dropForeignIfExists('reports', 'submitted_by', 'reports_submitted_by_foreign');
            $this->dropForeignIfExists('reports', 'region_id', 'reports_region_id_foreign');

            foreach ([
                'reports_region_id_created_at_index',
                'reports_region_id_workflow_status_index',
                'reports_category_priority_created_at_index',
                'reports_priority_index',
                'reports_workflow_status_index',
                'reports_escalation_level_index',
                'reports_latitude_index',
                'reports_longitude_index',
            ] as $index) {
                $this->dropIndexIfExists('reports', $index);
            }

            $columns = array_values(array_filter([
                'region_id','priority','workflow_status','escalation_level','description','rt_number','rw_number',
                'latitude','longitude','submitted_by','kecamatan_verified_by','kecamatan_verified_at','kota_received_at',
                'assigned_agency','agency_referred_at',
            ], fn (string $column) => Schema::hasColumn('reports', $column)));

            if ($columns !== []) {
                Schema::table('reports', fn (Blueprint $table) => $table->dropColumn($columns));
            }
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'region_id')) {
            $this->dropForeignIfExists('users', 'region_id', 'users_region_id_foreign');
            $this->dropIndexIfExists('users', 'users_role_region_id_index');
            Schema::table('users', fn (Blueprint $table) => $table->dropColumn('region_id'));
        }

        Schema::dropIfExists('regions');
    }

    private function addReportColumn(string $column, callable $definition): void
    {
        if (!Schema::hasColumn('reports', $column)) {
            Schema::table('reports', $definition);
        }
    }

    private function ensureIndex(string $table, array $columns, string $name): void
    {
        if ($this->indexExists($table, $name)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($columns, $name) {
            $blueprint->index($columns, $name);
        });
    }

    private function ensureForeignKey(string $table, string $column, string $referencesTable, string $referencesColumn, string $name): void
    {
        if ($this->foreignKeyExists($table, $column, $name)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column, $referencesTable, $referencesColumn, $name) {
            $blueprint->foreign($column, $name)
                ->references($referencesColumn)
                ->on($referencesTable)
                ->cascadeOnUpdate()
                ->nullOnDelete();
        });
    }

    private function indexExists(string $table, string $name): bool
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            return DB::table('information_schema.statistics')
                ->whereRaw('table_schema = DATABASE()')
                ->where('table_name', $table)
                ->where('index_name', $name)
                ->exists();
        }

        if ($driver === 'sqlite') {
            $safe = str_replace("'", "''", $table);
            foreach (DB::select("PRAGMA index_list('{$safe}')") as $index) {
                if (($index->name ?? null) === $name) {
                    return true;
                }
            }
        }

        return false;
    }

    private function foreignKeyExists(string $table, string $column, string $name): bool
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            return DB::table('information_schema.key_column_usage')
                ->whereRaw('table_schema = DATABASE()')
                ->where('table_name', $table)
                ->where('column_name', $column)
                ->where('constraint_name', $name)
                ->whereNotNull('referenced_table_name')
                ->exists();
        }

        if ($driver === 'sqlite') {
            $safe = str_replace("'", "''", $table);
            foreach (DB::select("PRAGMA foreign_key_list('{$safe}')") as $fk) {
                if (($fk->from ?? null) === $column) {
                    return true;
                }
            }
        }

        return false;
    }

    private function dropIndexIfExists(string $table, string $name): void
    {
        if (!$this->indexExists($table, $name)) {
            return;
        }
        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropIndex($name));
    }

    private function dropForeignIfExists(string $table, string $column, string $name): void
    {
        if (!$this->foreignKeyExists($table, $column, $name)) {
            return;
        }
        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropForeign($name));
    }
};
