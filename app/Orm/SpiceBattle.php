<?php

namespace App\Orm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * a Spice Battle is a lottery-like two-player game
 *
 * On each turn you and your enemy bet some amount of money.
 * By betting more than your opponent, you win the round.
 * By winning two rounds you win the battle and take all remaining spice of your opponent as well as _spice left on the battlefield_.
 * The _spice left on the battlefield_ is the minimum bet amount taken from bets of both players each round.
 * Everything beyond this minimum gets discarded.
 *
 * So the point is in reading your opponent.
 * If you think he is gonna bet a large amount, you should put the minimum so
 * that he lost the difference (discarded) whereas you lost almost nothing.
 * The most profitable situation for the winner is when you bet just barely
 * more than your opponent - you both lose almost same amount, but you won
 * the round on top of that, as well as preserved both bet values for yourself
 * when you win the second round.
 *
 * I have a hard time figuring out whether there is a profitable
 * strategy against a player that always bets random amount.
 * If there is, could invent some challenging AI later.
 * If not, this game will be meaningful only between human opponents.
 *
 * Another detail: you decide how much money to "take with you" before the battle.
 * This is another "read your opponent" thing.
 * If you take too much, you likely gonna loose more money than win,
 * and if you take too little, you likely won't win as well.
 *
 * @property int $id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property int $prize_spice;
 *
 * @property $competitors = new Collection([new Competitor()])
 */
class SpiceBattle extends Model
{
    public function competitors()
    {
        return $this->hasMany(Competitor::class);
    }
}
