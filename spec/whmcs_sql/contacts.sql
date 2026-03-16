SELECT
  c.userid as clientid,
  c.firstname AS firstname,
  c.lastname AS lastname,
  c.companyname AS companyname,
  c.email AS email,
  CONCAT(c.address1, ", ", c.address2) AS address,
  c.city AS city,
  c.state AS state,
  c.postcode AS zip,
  c.country AS country,
  c.phonenumber AS phonenumber
FROM tblcontacts c;
