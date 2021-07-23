/* Swiss payments from ESR to DTA
 * Copyright (C) 2016 Aarboard AG, Andre Schild, www.aarboard.ch
 */
/**
 * Author:  a.schild
 * Created: 05.01.2016

Payed invoices line by line

 */


CREATE TABLE IF NOT EXISTS llx_swisspayments_payl (
rowid INT NOT NULL AUTO_INCREMENT ,
fk_payh INT NOT NULL,
fk_payementfourn INT NOT NULL,
datec DATETIME,
tms TIMESTAMP NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
PRIMARY KEY ( rowid )
) ENGINE = InnoDB ;

