<?php
namespace App\Orm;

use Illuminate\Database\Eloquent\Model;

/**
 * this table stores various combinations of moves and "how good" they are after
 * generated through trying all possible first-second-third
 * moves and looking which of them lead to win more often
 * gonna use it for AI to make it smarter
 *
 * @property int $id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property int $p1_bet_1
 * @property int $p2_bet_1
 * @property int $p1_bet_2
 * @property int $p2_bet_2
 * @property int $p1_bet_3
 * @property int $p2_bet_3
 * @property double $win_rate
 */
class AiMove extends Model
{
}