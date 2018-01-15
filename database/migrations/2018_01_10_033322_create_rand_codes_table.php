<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateRandCodesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('rand_codes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('md5',100);
            $table->string('value',50);
            $table->string('path',255);
            $table->smallInteger('is_ok')->default(0);
            $table->bigInteger('times')->default(0);

            $table->integer('created_at');
            $table->integer('updated_at');

            $table->unique(['md5']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('rand_codes');
    }
}
