/* Swiss payments from ESR to DTA
 * Copyright (C) 2016 Aarboard AG, Andre Schild, www.aarboard.ch
 */
/**
 * Author:  a.schild
 * Created: 05.01.2016

Payment header file
 */

CREATE TABLE IF NOT EXISTS llx_swisspayments_payh (
rowid INT NOT NULL AUTO_INCREMENT ,
payident CHAR(80) NOT NULL,
datec DATETIME,
tms TIMESTAMP NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
dtafile CHAR(80),
PRIMARY KEY ( rowid ),
UNIQUE (payident)
) ENGINE = InnoDB ;

