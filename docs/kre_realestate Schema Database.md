# kre_realestate Schema Database

### kre_realestate_import_queue

| Field | Type | Null | Key | Default | Extra |
| --- | --- | --- | --- | --- | --- |
| id | bigint(20) unsigned | NO | PRI | NULL | auto_increment |
| session_id | varchar(50) | NO | MUL | NULL |  |
| item_type | enum('agency','property') | NO | MUL | NULL |  |
| item_id | varchar(50) | NO |  | NULL |  |
| status | enum('pending','processing','done','error','retry') | YES | MUL | pending |  |
| priority | int(11) | YES | MUL | 0 |  |
| retry_count | int(11) | YES |  | 0 |  |
| error_message | text | YES |  | NULL |  |
| wp_post_id | bigint(20) unsigned | YES |  | NULL |  |
| created_at | datetime | YES |  | CURRENT_TIMESTAMP |  |
| processed_at | datetime | YES |  | NULL |  |

### kre_realestate_sync_agency_tracking

| Field | Type | Null | Key | Default | Extra |
| --- | --- | --- | --- | --- | --- |
| agency_id | varchar(50) | NO | PRI | NULL |  |
| wp_post_id | bigint(20) unsigned | YES | MUL | NULL |  |
| agency_hash | varchar(32) | NO | MUL | NULL |  |
| data_snapshot | longtext | YES |  | NULL |  |
| last_import_date | datetime | NO | MUL | NULL |  |
| status | enum('active','inactive','deleted','error') | YES | MUL | active |  |
| provincia | varchar(10) | YES | MUL | NULL |  |
| created_date | datetime | YES |  | CURRENT_TIMESTAMP |  |
| updated_date | datetime | YES |  | CURRENT_TIMESTAMP | on update CURRENT_TIMESTAMP |

### kre_realestate_sync_tracking

| Field | Type | Null | Key | Default | Extra |
| --- | --- | --- | --- | --- | --- |
| property_id | int(11) | NO | PRI | NULL |  |
| wp_post_id | bigint(20) unsigned | YES | MUL | NULL |  |
| property_hash | varchar(32) | NO | MUL | NULL |  |
| data_snapshot | longtext | YES |  | NULL |  |
| last_import_date | datetime | NO | MUL | NULL |  |
| status | enum('active','inactive','deleted','error') | YES | MUL | active |  |
| provincia | varchar(10) | YES | MUL | NULL |  |
| category_id | int(11) | YES |  | NULL |  |
| price | decimal(15,2) | YES |  | NULL |  |
| created_date | datetime | YES |  | CURRENT_TIMESTAMP |  |
| updated_date | datetime | YES |  | CURRENT_TIMESTAMP | on update CURRENT_TIMESTAMP