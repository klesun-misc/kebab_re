<?php

require_once(__DIR__.'/../vendor/autoload.php');

// I don't know why, but these lines are needed for Laravel's DB to work
//$app = require_once __DIR__.'/../bootstrap/app.php';
//$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
//$response = $kernel->handle(
//    $request = Illuminate\Http\Request::capture()
//);

class Util
{
    private static function isList(array $container)
    {
        return array_keys($container) === range(0, count($container) - 1);
    }

    public static function varExportCompact($var, $margin = '', string $ind = '    ')
    {
        $result = false ? null
            : (is_array($var)
                ? '['.($ind ? PHP_EOL : '')
                    .(implode(','.($ind ? PHP_EOL : ' '), self::isList($var)
                        ? array_map(function($v) use ($margin, $ind) {
                            return $margin.$ind.self::varExportCompact($v, $margin.$ind);
                        }, $var)
                        : array_map(function($v, $k) use ($margin, $ind) {
                            return $margin.$ind.var_export($k, true).' => '.self::varExportCompact($v, $margin.$ind);
                        }, $var, array_keys($var))
                    ).($ind ? ','.PHP_EOL : '').$margin.']')
            : (is_string($var) && strpos($var, PHP_EOL) !== false
                ? 'implode(PHP_EOL, '.self::varExportCompact(explode('\n', $var), $margin).')'
            : (in_array(var_export($var, true), ['NULL', 'TRUE', 'FALSE'])
                ? strtolower(var_export($var, true))
            : var_export($var, true))));

        if ($ind && mb_strlen($result) < 1000) { // performance hack
            $minified = self::varExportCompact($var, '', '');
            if (mb_strlen($minified) < 60) {
                $result = $minified;
            }
        }

        return $result;
    }
}

class State
{
    public $p1Wins;
    public $p2Wins;
    public $p1Left;
    public $p2Left;
    public $p1Stolen;
    public $p2Stolen;
    public $prizeMoney;
}

/**
 * looks how much winning situations a led by
 * certain first move in the Spice Battle
 */
class Script
{
    const START_MONEY = 12;

    private $state;
    private $chains = [];

    public function __construct()
    {
        $this->state = new State();
    }

    private function parseBetChain(array $betChain)
    {
        $this->state->p1Wins = 0;
        $this->state->p2Wins = 0;
        $this->state->p1Left = static::START_MONEY;
        $this->state->p2Left = static::START_MONEY;
        $this->state->p1Stolen = 0;
        $this->state->p2Stolen = 0;
        $this->state->prizeMoney = 0;
        for ($i = 0; $i < count($betChain); $i += 2) {
            $p1Bet = $betChain[$i];
            $p2Bet = $betChain[$i + 1];

            $this->state->p1Left -= $p1Bet;
            $this->state->p2Left -= $p2Bet;
            $this->state->prizeMoney += min($p1Bet, $p2Bet) * 2;
            if ($p1Bet > $p2Bet) {
                $this->state->p1Wins += 1;
                $this->state->p2Stolen += $p1Bet - $p2Bet;
            } elseif ($p1Bet < $p2Bet) {
                $this->state->p2Wins += 1;
                $this->state->p1Stolen += $p2Bet - $p1Bet;
            }
        }
        return $this->state;
    }

    /** average with weights */
    private static function avgWeighted(array $pair)
    {
        $totalValue = 0;
        $totalWeight = 0;
        foreach ($pair as list($val, $weight)) {
            $totalValue = $val * $weight;
            $totalWeight += $weight;
        }
        return $totalWeight ? $totalValue / $totalWeight : null;
    }

    /**
     * make $p1BetToWinRate by thinking that p2 makes bets most profitable
     * to him with frequency proportional to the profitability
     * then calc the win rate for each possible move of p1 based on that
     */
    private static function reduceP2WinRates(array $p1BetToP2BetToWinRate)
    {
        if (!$p1BetToP2BetToWinRate) {
            return [];
        }

        $p2BetToP1BetToWinRate = [];
        foreach ($p1BetToP2BetToWinRate as $p1Bet => $p2BetToWinRate) {
            foreach ($p2BetToWinRate as $p2Bet => $winRate) {
                $p2BetToP1BetToWinRate[$p2Bet][$p1Bet] = $winRate;
            }
        }

        // first we calc p2 particular move probabilities
        // the "probability" is relational, 0.5 here is not 50% of choosing the bet
        $p2BetToProbability = array_map(function($p1BetToWinRate){
            $p1WinRate = array_sum($p1BetToWinRate) / count($p1BetToWinRate);
            // assuming that probability of p2 choosing this
            // move is the probability of win in such case
            // could square it or something to add contrast to 0.5 and 0.75
            return 1 - $p1WinRate;
        }, $p2BetToP1BetToWinRate);

        // and then we calc p1 win rates knowing the chances of each p2 move
        $reducedP1BetToWinRate = [];
        foreach ($p1BetToP2BetToWinRate as $p1Bet => $p2BetToWinRate) {
            $pairs = [];
            foreach ($p2BetToWinRate as $p2Bet => $winRate) {
                $probability = $p2BetToProbability[$p2Bet];
                $pairs[] = [$winRate, $probability];
            }
            $reducedP1BetToWinRate[$p1Bet] = static::avgWeighted($pairs);
        }
        return $reducedP1BetToWinRate;
    }

    private function getWinRate(array $betChain)
    {
        if (count($betChain) >= 6) {
            // depth limit, battle could go forever if both players always bet 0
            return null;
        }
        $before = $this->parseBetChain($betChain);
        $p1MaxBet = $before->p1Wins > 0 ? $before->p1Left : $before->p1Left - 1;
        $p2MaxBet = $before->p2Wins > 0 ? $before->p2Left : $before->p2Left - 1;
        $p1BetToP2BetToWinRate = [];
        $p1Chances = [];
        for ($p1Bet = $p1MaxBet; $p1Bet >= 0; --$p1Bet) {
            $betChain[] = $p1Bet;
            $p2BetChances = [];
            for ($p2Bet = $p2MaxBet; $p2Bet >= 0; --$p2Bet) {
                if ($p1Bet === 0 && $p2Bet === 0) {
                    continue; // both betting 0 can go for eternity
                }

                $betChain[] = $p2Bet;
                $after = $this->parseBetChain($betChain);
                $p1Prize = $after->p1Stolen;
                $p2Prize = $after->p2Stolen;
                if ($after->p1Wins >= 2) {
                    $p1Prize += $after->prizeMoney + $after->p2Left;
                    $isDone = true;
                } elseif ($after->p2Wins >= 2) {
                    $p2Prize += $after->prizeMoney + $after->p1Left;
                    $isDone = true;
                } elseif ($after->p1Left == 0 && $after->p2Left == 0) {
                    $p1Prize += $after->prizeMoney / 2;
                    $p2Prize += $after->prizeMoney / 2;
                    $isDone = true;
                } else {
                    $isDone = false;
                }
                $advantage = $p1Prize - $p2Prize;
                if ($isDone) {
                    print('[['.implode(',', $betChain).'], '.$advantage.'],'.PHP_EOL);
                    if ($advantage > 0) {
                        $winRate = 1;
                    } elseif ($advantage < 0) {
                        $winRate = 0;
                    } else {
                        $winRate = null;
                    }
//                    $moveWinRates[$p1Bet][$p2Bet] = $advantage;
//                    yield [];
//					$moveWinRates[] = [$betChain, $winRate['advantage']];
				} else {
                    $winRate = static::getWinRate($betChain);
//                    $moveWinRates[$p1Bet][$p2Bet] = $moreMoves;
//                    yield from $moreMoves;
//                    $moveWinRates = array_merge($moveWinRates, );
                    //$winRate['nextMoves'][] = static::getWinRates($betChain);
                }
                if (!is_null($winRate)) {
                    $p1BetToP2BetToWinRate[$p1Bet][$p2Bet] = $winRate;
                    $p2BetChances[] = $winRate;
                }
                array_pop($betChain);
            }
//            if ($p2BetChances) {
//                $winRate = array_sum($p2BetChances) / count($p2BetChances);
//                print('^^'.implode(',', $betChain).' | '.$winRate.PHP_EOL);
//                $this->chains[] = [$betChain, $winRate];
//                $p1Chances[] = $winRate;
//                /** @debyg */
//                if (count($betChain) === 1) {
//                    print('--'.$betChain[0].' | '.$winRate.PHP_EOL);
//                }
//            }
            array_pop($betChain);
        }
        $p1BetToWinRate = static::reduceP2WinRates($p1BetToP2BetToWinRate);
        foreach ($p1BetToWinRate as $p1Bet => $winRate) {
            $betChain[] = $p1Bet;
            print('^^'.implode(',', $betChain).' | '.$winRate.PHP_EOL);
            print('    '.json_encode($p1BetToP2BetToWinRate).PHP_EOL);
            $this->chains[] = [$betChain, $winRate];
            $p1Chances[] = $winRate;
            array_pop($betChain);
        }
        if ($p1BetToWinRate) {
            $winRate = max($p1BetToWinRate);
        } else {
            $winRate = null;
        }
        print('--'.implode(',', $betChain).' | '.var_export($winRate, true).PHP_EOL);

        return $winRate;
    }
    
    //~ private static function deepKeys($tree)
    //~ {
		//~ $pairs = [];
		//~ $betChain = [];
		//~ foreach($tree as $pqBet => $p2Bets) {
			//~ foreach ($p2Bets as $p2Bets => $subTree) {
				//~ if (is_int($subTree)) {
					//~ $pairs[] = [];
				//~ }
			//~ }
		//~ }
		//~ return $pairs;
	//~ }

    public function main()
    {
        $winRates = [];
        $moveWinRates = $this->getWinRate($winRates);
        print(Util::varExportCompact($moveWinRates));
        $qMarkBrac = '(?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
        $sql = implode(PHP_EOL, [
            'INSERT INTO ai_moves',
            '(win_rate, p1_bet_1, p2_bet_1, p1_bet_2, p2_bet_2, p1_bet_3, p2_bet_3, p1_wins, p2_wins, p1_left, p2_left, prize, p1_stolen, p2_stolen)',
            'VALUES '.implode(',', array_fill(0, count($this->chains), $qMarkBrac)).';'
        ]);
        $mkRowValues = function($pair){
            list($betChain, $winRate) = $pair;
            $parsed = $this->parseBetChain(array_slice($betChain, 0, -1));
            return [
                // win_rate, p1_bet_1, p2_bet_1, p1_bet_2, p2_bet_2, p1_bet_3, p2_bet_3
                $winRate,
                $betChain[0] ?? null, $betChain[1] ?? null, $betChain[2] ?? null,
                $betChain[3] ?? null, $betChain[4] ?? null, $betChain[5] ?? null,
                // p1_wins, p2_wins, p1_left, p2_left, prize, p1_stolen, p2_stolen
                $parsed->p1Wins, $parsed->p2Wins, $parsed->p1Left, $parsed->p2Left,
                $parsed->prizeMoney, $parsed->p1Stolen, $parsed->p2Stolen,
            ];
        };
        $allValues = array_merge(...array_map($mkRowValues, $this->chains));

//        $pdo = new \PDO('mysql:host=localhost;dbname=kebab_republic', 'php', 'chlen456');
//        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
//        print('writing to DB...'.PHP_EOL);
//        $ok = $pdo->prepare($sql)->execute($allValues);
//        print('done '.var_export($ok, true).' '.$pdo->errorCode().' '.json_encode($pdo->errorInfo()).PHP_EOL);
    }
}
(new Script())->main();
