<?php

namespace Meow;
use \Doctrine\DBAL\Connection;

class Util
{

    public static function AssertUniqueness($table, $value, $field, Connection $db)
    {
        $qb = $db->createQueryBuilder();
        $qb->select('*')
            ->from($table)
            ->where($field . ' = ?' )
            ->setParameter(0, $value);
        $test = $qb->execute();
        if($test->rowCount() > 0)
            return false;
        else
            return true;
    }

    public static function GenerateCustomUrl()
    {
        $token = "";
        $alphabet = "123456789abcdefghijklmnopqrstuvwkyz";
        $alphabetLength = strlen($alphabet);
        $length = 10;
        for ($i = 0; $i < $length; $i++) {
            $token .= $alphabet[self::RandomOffset(0, $alphabetLength)];
        }
        return $token;
    }

    private static function RandomOffset($min, $max)
    {
        $range = $max - $min;
        if ($range < 1) return $min; // not so random...
        $log = ceil(log($range, 2));
        $bytes = (int)($log / 8) + 1; // length in bytes
        $bits = (int)$log + 1; // length in bits
        $filter = (int)(1 << $bits) - 1; // set all lower bits to 1
        do {
            $rnd = hexdec(bin2hex(openssl_random_pseudo_bytes($bytes)));
            $rnd = $rnd & $filter; // discard irrelevant bits
        } while ($rnd >= $range);
        return $min + $rnd;
    }
}