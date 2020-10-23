<?php
namespace App\Versioned;

use App\Orm\AiMove;
use App\Orm\Competitor;
use App\Orm\SpiceBattle;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class JsApiController
{
    // player id-s
    const ANY_USER = 1;
    const AI = 2;

    // DB has all possible moves in range 0-12
    // player gonna play greater values - this
    // constant for AI logic to use DB properly
//    const AI_MULT = 12 / 100;
    const START_MONEY = 12;
    const AI_MULT = 12 / self::START_MONEY;

    private static function rqToData(Request $request): Strict
    {
        $arr = strpos($request->getContentType(), 'json') !== false
            ? json_decode($request->getContent(), true)
            : ($request->request->all() ?: $request->query->all());
        return Strict::m($arr);
    }

    public static function startBattle(Request $request)
    {
        $battle = new SpiceBattle();
        $human = new Competitor(['spice_left' => static::START_MONEY, 'player_id' => static::ANY_USER]);
        $ai = new Competitor(['spice_left' => static::START_MONEY, 'player_id' => static::AI]);
        $battle->save();
        $battle->competitors()->saveMany([$human, $ai]);
        return new JsonResponse([
            'battleId' => $battle->id,
            'human' => $human,
            'ai' => $ai,
        ]);
    }

    private static function randomByWeight(array $valToWeight)
    {
        // ^2 all weights few times to make
        // 0.95 significantly more frequent than 0.7
        $contrast = function($weight){return pow($weight, 4);};
        $valToWeight = array_map($contrast, $valToWeight);

        $totalWeight = array_sum($valToWeight);
        $progress = mt_rand() / mt_getrandmax();
        $pointer = $progress * $totalWeight;
        $passed = 0;
        foreach ($valToWeight as $val => $weight) {
            $passed += $weight;
            if ($pointer <= $passed) {
                return $val;
            }
        }
        return null;
    }

    private static function chooseAiBetAmount(SpiceBattle $battle)
    {
        $human = $battle->competitors->where('player_id', static::ANY_USER)->first();
        $ai = $battle->competitors->where('player_id', static::AI)->first();
        $ai->;
        $useRandomBot = false;
        $randomAmount = $ai->rounds_won == 0
            ? rand(0, $ai->spice_left - 1)
            : rand(0, $ai->spice_left);
        if ($useRandomBot) {
            return $randomAmount;
        } else {
            // p1_wins | p2_wins | p1_LEFT | p2_LEFT | prize | p1_stolen | p2_stolen
            $query = implode(PHP_EOL, [
                'TRUE',
                '    AND p1_wins = '.$ai->rounds_won,
                '    AND p2_wins = '.$human->rounds_won,
                '    AND p1_left >= '.floor($ai->spice_left * static::AI_MULT).' AND p1_left <= '.ceil($ai->spice_left * static::AI_MULT),
                '    AND p2_left >= '.floor($human->spice_left * static::AI_MULT).' AND p2_left <= '.ceil($human->spice_left * static::AI_MULT),
                '    AND prize >= '.floor($battle->prize_spice * static::AI_MULT).' AND prize <= '.ceil($battle->prize_spice * static::AI_MULT),
                '    AND p1_stolen >= '.floor($ai->spice_stolen * static::AI_MULT).' AND p1_stolen <= '.ceil($ai->spice_stolen * static::AI_MULT),
                '    AND p2_stolen >= '.floor($human->spice_stolen * static::AI_MULT).' AND p2_stolen <= '.ceil($human->spice_stolen * static::AI_MULT),
            ]);
            DB::enableQueryLog();
            $moves = AiMove::whereRaw(DB::raw($query))
                ->orderByRaw(DB::raw(implode(PHP_EOL, [
                    'IF(p1_bet_1 IS NULL, 0, 1),',
                    'IF(p1_bet_2 IS NULL, 0, 1),',
                    'IF(p1_bet_3 IS NULL, 0, 1)',
                ])));
            if ($moves->count() == 0) {
                dd(DB::getQueryLog());
                return $randomAmount;
//            } elseif ($rows->count() > 1) {
//                throw new \Exception('zalupa rows '.$rows->count().' '.$query);
            } else {
                $betToWinRate = [];
                $moves->get();
                foreach ($moves->get() as $move) {
                    $betChain = array_filter([$move->p1_bet_1, $move->p1_bet_2, $move->p1_bet_3]);
                    $bet = array_pop($betChain);
                    $betToWinRate[$bet] = $move->win_rate;
                }
                if ($betToWinRate) {
                    $isSureWin = function($winRate){return $winRate >= 0.9999999;};
                    if ($sureWins = array_filter($betToWinRate, $isSureWin)) {
                        $betToWinRate = $sureWins;
                    }
                    $bestBet = static::randomByWeight($betToWinRate);
                    return min(round($bestBet / static::AI_MULT), $ai->spice_left);
                } else {
                    return $randomAmount;
                }
            }
        }
    }

    public static function betSpice(Request $request)
    {
        $rawParams = self::rqToData($request);
        try {
            $battleId = $rawParams->get('battleId');
            $betAmount = $rawParams->get('betAmount');
        } catch (\Exception $exc) {
            $result = ['error' => 'You did not provide some mandatory field: '.$exc->getMessage()];
            return new JsonResponse($result, 400);
        }
        $battle = SpiceBattle::find($battleId);

        $human = $battle->competitors->where('player_id', static::ANY_USER)->first();
        $ai = $battle->competitors->where('player_id', static::AI)->first();

        if ($betAmount < 0 || $betAmount > $human->spice_left) {
            $result = ['error' => 'Your bet is too high: '.$betAmount.', you do not have that much left: '.$human->spice_left];
            return new JsonResponse($result, 400);
        }
        $aiBetAmount = static::chooseAiBetAmount($battle, $ai, $human);

        $human->spice_left -= $betAmount;
        $ai->spice_left -= $aiBetAmount;

        if ($betAmount > $aiBetAmount) {
            $roundWinner = $human;
            $roundLooser = $ai;
        } elseif ($betAmount < $aiBetAmount) {
            $roundWinner = $ai;
            $roundLooser = $human;
        } else {
            $roundWinner = null;
            $roundLooser = null;
        }
        $minBet = min($betAmount, $aiBetAmount);
        $maxBet = max($betAmount, $aiBetAmount);
        if ($roundWinner && $roundLooser) {
            $roundWinner->rounds_won += 1;
            $roundLooser->spice_stolen += $maxBet - $minBet;
        }
        $battle->prize_spice += $minBet * 2;

        $battle->save();
        $human->save();
        $ai->save();

        $result = [
            'battleId' => $battle->id,
            'human' => $human,
            'ai' => $ai,
            'humanBet' => $betAmount,
            'aiBet' => $aiBetAmount,
        ];
        return new JsonResponse($result);
    }
}
