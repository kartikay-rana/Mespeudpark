<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRecordingListsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('recording_lists', function (Blueprint $table) {
            $table->id();
            $table->string('recordID');
             $table->string('meetingID');
            $table->string('startTime');
            $table->string('endTime');
             $table->string('title');
             $table->string('url');
              $table->string('status')->default(0);
              
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
        Schema::dropIfExists('recording_lists');
    }
}
