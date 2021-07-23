/* Swiss payments from ESR to DTA
 * Copyright (C) 2016 Aarboard AG, Andre Schild, www.aarboard.ch
 */

CREATE TABLE IF NOT EXISTS llx_swisspayments_soc (
rowid INT NOT NULL AUTO_INCREMENT ,
fk_societe INT NOT NULL,
startorderno INT,
endorderno INT,
pcaccount CHAR(12),
esrid CHAR(12),
PRIMARY KEY ( rowid )
) ENGINE = InnoDB ;


