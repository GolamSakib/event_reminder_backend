<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEventsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_reminder_id_from_browser'); // Predefined prefix format
            $table->string('event_reminder_id')->unique();                // Predefined prefix format
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamp('startDate');
            $table->timestamp('endDate');
            $table->boolean('completed')->default(false);
            $table->timestamps();
            $table->boolean('is_notification_sent')->default(false);

            $table->index('created_by');
            $table->index('completed');
        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('events');
    }
}
