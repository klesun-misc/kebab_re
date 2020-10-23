<?php
namespace App\Versioned;

/**
 * this class is handy when you need to validate params
 * you got from client to match expected structure
 * it also provides IDE with type information of params
 * Usage:
 * try {
 *     $classPath = Strict::m($params)->get('classPath');
 *     $methodName = Strict::m($params)->get('methodName');
 *     $paramData = Strict::m($params)->get('paramData');
 *     $ctorArgs = $params['ctorArgs'] ?? null;
 * } catch (\Exception $exc) {
 *     yield ['you did not provide some mandatory field: '.PHP_EOL.$exc->getMessage(), null];
 *     return;
 * }
 */
class Strict
{
    private $subj;

    public function __construct($subj)
    {
        $this->subj = $subj;
    }

    public static function m(array $subj): self
    {
        return new self($subj);
    }

    /** @throws \Exception */
    public function get(string ...$keys)
    {
        $result = $this->subj;
        $used = [];
        foreach ($keys as $key) {
            $used[] = $key;
            if (array_key_exists($key, $result)) {
                $result = $result[$key];
            } else {
                throw new \Exception('no such key ['.implode(',', $used).']');
            }
        }
        return $result;
    }
}
