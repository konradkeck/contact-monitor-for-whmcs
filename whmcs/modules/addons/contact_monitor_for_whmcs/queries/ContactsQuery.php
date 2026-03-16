<?php

use WHMCS\Database\Capsule;

class ContactsQuery
{
    public static function run(array $params = []): array
    {
        $after_id = (int)($params['after_id'] ?? 0);
        $limit    = (int)($params['limit']    ?? 100);

        $sql = "
SELECT
  c.id AS contactid,
  c.userid AS clientid,
  c.firstname AS firstname,
  c.lastname AS lastname,
  c.companyname AS companyname,
  c.email AS email,
  CONCAT(c.address1, ', ', c.address2) AS address,
  c.city AS city,
  c.state AS state,
  c.postcode AS zip,
  c.country AS country,
  c.phonenumber AS phonenumber
FROM tblcontacts c
WHERE c.id > ?
ORDER BY c.id ASC
LIMIT ?
";

        try {
            $rows = Capsule::select($sql, [$after_id, $limit]);
            return array_map(fn($r) => (array)$r, $rows);
        } catch (\Throwable $e) {
            throw new \Exception('query_failed');
        }
    }
}
