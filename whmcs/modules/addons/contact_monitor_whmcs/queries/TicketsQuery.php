<?php

use WHMCS\Database\Capsule;

class TicketsQuery
{
    public static function run(array $params = []): array
    {
        $after_sent_at   = $params['after_sent_at']   ?: '1970-01-01 00:00:00';
        $after_ticket_id = (int)($params['after_ticket_id'] ?? 0);
        $limit           = (int)($params['limit']           ?? 100);

        $sql = "
SELECT
  m.ticket_id,
  m.msg_id,
  m.sent_at,
  d.name AS department_name,
  t.status AS status,
  t.title AS title,

  CASE WHEN COALESCE(NULLIF(m.admin,''),'') <> '' THEN 'admin' ELSE 'client' END AS sender_type,
  CASE WHEN COALESCE(NULLIF(m.admin,''),'') <> '' THEN 'to_client' ELSE 'from_client' END AS direction,

  NULLIF(t.userid, 0) AS client_userid,

  CASE
    WHEN COALESCE(NULLIF(m.admin,''),'') <> '' THEN
      COALESCE(
        NULLIF(TRIM(CONCAT(ad.firstname,' ',ad.lastname)), ''),
        NULLIF(ad.username, ''),
        NULLIF(m.admin,'')
      )
    ELSE
      COALESCE(
        NULLIF(m.name,''),
        NULLIF(TRIM(CONCAT(c.firstname,' ',c.lastname)), ''),
        NULLIF(c.companyname,''),
        CONCAT('client:', t.userid)
      )
  END AS sender_name,

  CASE
    WHEN COALESCE(NULLIF(m.admin,''),'') <> '' THEN
      NULLIF(ad.email,'')
    ELSE
      COALESCE(NULLIF(m.email,''), NULLIF(c.email,''), NULL)
  END AS sender_email,

  m.message
FROM
(
  SELECT x.ticket_id, x.msg_id, x.sent_at, x.userid, x.name, x.email, x.admin, x.message
  FROM
  (
    SELECT
      t0.id AS ticket_id,
      CONCAT(t0.id, '_0') AS msg_id,
      t0.date AS sent_at,
      t0.userid,
      t0.name,
      t0.email,
      t0.admin,
      t0.message
    FROM tbltickets t0
    WHERE t0.date >= DATE_SUB(CURDATE(), INTERVAL 3 YEAR)

    UNION ALL

    SELECT
      r0.tid AS ticket_id,
      CONCAT(r0.tid, '_', r0.id) AS msg_id,
      r0.date AS sent_at,
      r0.userid,
      r0.name,
      r0.email,
      r0.admin,
      r0.message
    FROM tblticketreplies r0
    WHERE r0.date >= DATE_SUB(CURDATE(), INTERVAL 3 YEAR)
  ) x
  WHERE (x.sent_at > ? OR (x.sent_at = ? AND x.ticket_id > ?))
  ORDER BY x.sent_at ASC, x.ticket_id ASC
  LIMIT ?
) m
INNER JOIN tbltickets t ON t.id = m.ticket_id
LEFT JOIN tblticketdepartments d ON d.id = t.did
LEFT JOIN tblclients c ON c.id = t.userid
LEFT JOIN tbladmins ad ON ad.id = COALESCE(
  (SELECT id FROM tbladmins WHERE username = m.admin LIMIT 1),
  (SELECT id FROM tbladmins WHERE NULLIF(m.admin,'') IS NOT NULL AND id = CAST(m.admin AS UNSIGNED) LIMIT 1),
  (SELECT id FROM tbladmins WHERE LOWER(TRIM(CONCAT(firstname,' ',lastname))) = LOWER(TRIM(m.admin)) LIMIT 1)
)
ORDER BY m.sent_at ASC, m.ticket_id ASC
";

        try {
            $rows = Capsule::select($sql, [
                $after_sent_at,
                $after_sent_at,
                $after_ticket_id,
                $limit,
            ]);
            return array_map(fn($r) => (array)$r, $rows);
        } catch (\Throwable $e) {
            throw new \Exception('query_failed');
        }
    }
}
