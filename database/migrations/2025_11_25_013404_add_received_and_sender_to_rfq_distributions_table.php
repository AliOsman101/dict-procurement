<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddReceivedAndSenderToRfqDistributionsTable extends Migration
{
    public function up()
    {
        Schema::table('rfq_distributions', function (Blueprint $table) {
            $table->timestamp('received_at')->nullable()->after('sent_at');
            $table->unsignedBigInteger('sender_id')->nullable()->after('received_at');

    
        });
    }

    public function down()
    {
        Schema::table('rfq_distributions', function (Blueprint $table) {
        
            $table->dropColumn(['received_at', 'sender_id']);
        });
    }
}
