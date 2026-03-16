SELECT
c.id as clientid,
c.firstname AS firstname,
c.lastname AS lastname,
c.companyname AS companyname,
c.email AS email,
CONCAT(c.address1, ", ", c.address2) AS address,
c.city AS city,
c.state AS state,
c.postcode AS zip,
c.country AS country,
c.phonenumber AS phonenumber,
c.datecreated AS datecreated,
c.lastlogin AS lastlogin,

ROUND(
COALESCE(SUM(a.amountin * (usd.rate / cur.rate)), 0),
2
) AS total_revenue,

ROUND(
COALESCE(SUM(
CASE
WHEN a.date >= DATE_SUB(CURDATE(), INTERVAL 3 YEAR)
THEN a.amountin * (usd.rate / cur.rate)
ELSE 0
END
), 0),
2
) AS yr3_revenue,

'USD' AS currency
FROM tblclients c
JOIN tblcurrencies cur ON cur.id = c.currency
JOIN tblcurrencies usd ON usd.id = 1
LEFT JOIN tblaccounts a
ON a.userid = c.id
AND a.amountin > 0
AND a.refundid = 0
GROUP BY c.id
ORDER BY total_revenue DESC;
