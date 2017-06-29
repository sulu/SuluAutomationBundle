# Upgrade

## dev-develop

### Fake-Request for task handlers
 
The task was extended by the column `host` and `scheme` to fake a similar request. This will ensure that the Cache
will be delete correctly.

Update your database by running following SQL-Statement:

```sql
ALTER TABLE au_task ADD scheme VARCHAR(5) DEFAULT '' NOT NULL, ADD host VARCHAR(255) DEFAULT '' NOT NULL;
ALTER TABLE au_task CHANGE scheme scheme VARCHAR(5) NOT NULL, CHANGE host host VARCHAR(255) NOT NULL;
```
