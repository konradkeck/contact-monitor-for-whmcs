SELECT
  x.userid as clientid,
  x.serviceid as serviceid,
  x.productid as productid,
  x.product_name as product_name,
  x.parent as parent,
  x.type as type,
  x.status as service_status,
  x.start_date as start_date,
  x.termination_date as termination_date,
  COALESCE(c.cancelled, 0) AS cancelled,
  c.cancel_type as cancel_type,
  c.cancel_reason as cancel_reason,
  COALESCE(r.total_revenue, 0.00) AS total_revenue,
  GREATEST(COALESCE(r.paid_invoice_count, 0) - 1, 0) AS renewal_count
FROM
(
  SELECT
    h.userid AS userid,
    h.id AS serviceid,
    h.packageid AS productid,
    p.name AS product_name,
    NULL AS parent,
    'product' AS type,
    h.domainstatus AS status,
    CAST(h.regdate AS DATE) AS start_date,
    CAST(
      CASE
        WHEN h.termination_date IS NULL OR h.termination_date = '0000-00-00' THEN NULL
        ELSE h.termination_date
      END
    AS DATE) AS termination_date,
    h.id AS relid,
    'Hosting' AS reltype
  FROM tblhosting h
  LEFT JOIN tblproducts p
    ON p.id = h.packageid

  UNION ALL

  SELECT
    h2.userid AS userid,
    ha.id AS serviceid,
    ha.addonid AS productid,
    a.name AS product_name,
    ha.hostingid AS parent,
    'addon' AS type,
    ha.status AS status,
    CAST(ha.regdate AS DATE) AS start_date,
    CAST(
      CASE
        WHEN ha.termination_date IS NULL OR ha.termination_date = '0000-00-00' THEN NULL
        ELSE ha.termination_date
      END
    AS DATE) AS termination_date,
    ha.id AS relid,
    'Addon' AS reltype
  FROM tblhostingaddons ha
  LEFT JOIN tblhosting h2
    ON h2.id = ha.hostingid
  LEFT JOIN tbladdons a
    ON a.id = ha.addonid
) x
LEFT JOIN
(
  SELECT
    ii.type AS reltype,
    ii.relid AS relid,
    SUM(ii.amount) AS total_revenue,
    COUNT(DISTINCT ii.invoiceid) AS paid_invoice_count
  FROM tblinvoiceitems ii
  INNER JOIN
  (
    SELECT DISTINCT a.invoiceid
    FROM tblaccounts a
    WHERE a.invoiceid <> 0
      AND a.amountin > 0
      AND a.refundid = 0
  ) paid
    ON paid.invoiceid = ii.invoiceid
  WHERE ii.type IN ('Hosting','Addon')
  GROUP BY ii.type, ii.relid
) r
  ON r.relid = x.relid
 AND r.reltype = x.reltype
LEFT JOIN
(
  SELECT
    cc.relid,
    1 AS cancelled,
    cc.type AS cancel_type,
    cc.reason AS cancel_reason
  FROM tblcancelrequests cc
  INNER JOIN
  (
    SELECT relid, MAX(date) AS max_date
    FROM tblcancelrequests
    GROUP BY relid
  ) last_cc
    ON last_cc.relid = cc.relid
   AND last_cc.max_date = cc.date
) c
  ON c.relid = x.relid
 AND x.reltype = 'Hosting'
ORDER BY start_date DESC
LIMIT 100;
