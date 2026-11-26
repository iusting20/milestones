<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comm_notes_tmp', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->nullable()->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->date('note_date');
            $table->timestamps();

            $table->index(['project_id', 'note_date', 'id']);
            $table->index(['person_id', 'note_date', 'id']);
        });

        $rows = DB::table('comm_notes')->orderBy('id')->get();
        foreach ($rows as $row) {
            DB::table('comm_notes_tmp')->insert([
                'id' => $row->id,
                'project_id' => $row->project_id,
                'person_id' => null,
                'body' => $row->body,
                'note_date' => $row->note_date,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);
        }

        Schema::drop('comm_notes');
        Schema::rename('comm_notes_tmp', 'comm_notes');
        $this->renameTmpIndexes();
    }

    public function down(): void
    {
        Schema::create('comm_notes_tmp', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->date('note_date');
            $table->timestamps();

            $table->index(['project_id', 'note_date', 'id']);
        });

        $rows = DB::table('comm_notes')->whereNotNull('project_id')->orderBy('id')->get();
        foreach ($rows as $row) {
            DB::table('comm_notes_tmp')->insert([
                'id' => $row->id,
                'project_id' => $row->project_id,
                'body' => $row->body,
                'note_date' => $row->note_date,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);
        }

        Schema::drop('comm_notes');
        Schema::rename('comm_notes_tmp', 'comm_notes');
        $this->renameTmpIndexes();
    }

    private function renameTmpIndexes(): void
    {
        $indexes = [
            'comm_notes_tmp_project_id_note_date_id_index' => 'comm_notes_project_id_note_date_id_index',
            'comm_notes_tmp_person_id_note_date_id_index' => 'comm_notes_person_id_note_date_id_index',
        ];

        foreach ($indexes as $from => $to) {
            $exists = DB::select('SELECT 1 FROM sqlite_master WHERE type = ? AND name = ?', ['index', $from]);
            if ($exists) {
                DB::statement('DROP INDEX IF EXISTS "'.$from.'"');
            }
        }

        Schema::table('comm_notes', function (Blueprint $table) {
            $sm = Schema::getConnection()->getSchemaBuilder();
            $names = collect($sm->getIndexes('comm_notes'))->pluck('name');
            if (! $names->contains('comm_notes_project_id_note_date_id_index')) {
                $table->index(['project_id', 'note_date', 'id']);
            }
            if (! $names->contains('comm_notes_person_id_note_date_id_index')) {
                $table->index(['person_id', 'note_date', 'id']);
            }
        });
    }
};
