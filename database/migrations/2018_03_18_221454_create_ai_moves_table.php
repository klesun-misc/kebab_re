<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAiMovesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ai_moves', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();
            $table->integer('p1_bet_1')->nullable();
            $table->integer('p2_bet_1')->nullable();
            $table->integer('p1_bet_2')->nullable();
            $table->integer('p2_bet_2')->nullable();
            $table->integer('p1_bet_3')->nullable();
            $table->integer('p2_bet_3')->nullable();
            $table->float('win_rate');

            $table->integer('p1_wins')->default(0);
            $table->integer('p2_wins')->default(0);
            $table->integer('p1_left')->default(0);
            $table->integer('p2_left')->default(0);
            $table->integer('prize')->default(0);
            $table->integer('p1_stolen')->default(0);
            $table->integer('p2_stolen')->default(0);

            // '    AND p1_wins = '.$ai->rounds_won,
            // '    AND p2_wins = '.$human->rounds_won,
            // '    AND p1_LEFT >= '.floor($ai->spice_left * static::AI_MULT).' AND p1_LEFT <= '.ceil($ai->spice_left * static::AI_MULT),
            // '    AND p2_LEFT >= '.floor($human->spice_left * static::AI_MULT).' AND p2_LEFT <= '.ceil($human->spice_left * static::AI_MULT),
            // '    AND prize >= '.floor($battle->prize_spice * static::AI_MULT).' AND prize <= '.ceil($battle->prize_spice * static::AI_MULT),
            // '    AND p1_stolen >= '.floor($ai->spice_stolen * static::AI_MULT).' AND p1_stolen <= '.ceil($ai->spice_stolen * static::AI_MULT),
            // '    AND p2_stolen >= '.floor($human->spice_stolen * static::AI_MULT).' AND p2_stolen <= '.ceil($human->spice_stolen * static::AI_MULT),

            $table->index(['p1_bet_1', 'p2_bet_1', 'p1_bet_2', 'p2_bet_2', 'p1_bet_3', 'p2_bet_3'], 'all_bet_key');
            $table->index(['p1_wins', 'p2_wins', 'p1_left', 'p2_left', 'prize', 'p1_stolen', 'p2_stolen'], 'state_key');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ai_moves');
    }
}
