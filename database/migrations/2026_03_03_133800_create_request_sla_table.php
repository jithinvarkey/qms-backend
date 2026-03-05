<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRequestSlaTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
   Schema::create('request_status_sla', function (Blueprint $table) {
    $table->id();
    $table->foreignId('request_id')->constrained('requests')->onDelete('cascade');
    $table->foreignId('status_id')->constrained('statuses');
    $table->timestamp('entered_at');
    $table->timestamp('exited_at')->nullable();
    $table->foreignId('changed_by')->constrained('users');
    $table->timestamps();
});

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('request_status_sla');
    }
}
