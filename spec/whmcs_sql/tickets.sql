SELECT
  m.ticket_id,
  m.sent_at,
  d.name AS department_name,
  t.status AS status,
  t.title AS title,

  CASE WHEN COALESCE(NULLIF(m.admin,''),'') <> '' THEN 'admin' ELSE 'client' END AS sender_type,
  CASE WHEN COALESCE(NULLIF(m.admin,''),'') <> '' THEN 'to_client' ELSE 'from_client' END AS direction,

  NULLIF(t.userid,0) AS client_userid,

  CASE
    WHEN COALESCE(NULLIF(m.admin,''),'') <> '' THEN
      COALESCE(
        NULLIF(TRIM(CONCAT(ad.firstname,' ',ad.lastname)),''),

        NULLIF(ad.username,''),
        NULLIF(m.admin,'')
      )
    ELSE
      COALESCE(
        NULLIF(m.name,''),
        NULLIF(TRIM(CONCAT(c.firstname,' ',c.lastname)),''),

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
  SELECT x.ticket_id, x.sent_at, x.userid, x.name, x.email, x.admin, x.message
  FROM
  (
    SELECT
      t0.id AS ticket_id,
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
      r0.date AS sent_at,
      r0.userid,
      r0.name,
      r0.email,
      r0.admin,
      r0.message
    FROM tblticketreplies r0
    WHERE r0.date >= DATE_SUB(CURDATE(), INTERVAL 3 YEAR)
  ) x
  ORDER BY x.sent_at DESC
  LIMIT 100
) m
INNER JOIN tbltickets t
  ON t.id = m.ticket_id
LEFT JOIN tblticketdepartments d
  ON d.id = t.did
LEFT JOIN tblclients c
  ON c.id = t.userid
LEFT JOIN tbladmins ad
  ON ad.username = m.admin
  OR ad.id = CASE
    WHEN m.admin REGEXP '^[0-9]+$' THEN CAST(m.admin AS UNSIGNED)
    ELSE NULL
  END
  OR LOWER(TRIM(CONCAT(ad.firstname,' ',ad.lastname))) = LOWER(TRIM(m.admin))
ORDER BY m.ticket_id DESC, m.sent_at ASC;
