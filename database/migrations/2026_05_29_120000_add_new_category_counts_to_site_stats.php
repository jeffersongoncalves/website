<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var list<string>
     */
    private array $columns = [
        'php_package',
        'javascript_package',
        'css_framework',
        'application',
        'learning_resource',
        'awesome_list',
        'mobile_library',
    ];

    public function up(): void
    {
        Schema::table('site_stats', function (Blueprint $table) {
            foreach ($this->columns as $column) {
                if (! Schema::hasColumn('site_stats', $column)) {
                    $table->unsignedInteger($column)->default(0)->after('youtube_channel');
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('site_stats', function (Blueprint $table) {
            $table->dropColumn($this->columns);
        });
    }
};
