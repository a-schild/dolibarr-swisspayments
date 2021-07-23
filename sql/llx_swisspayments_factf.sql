/* Swiss payments from ESR to DTA
 * Copyright (C) 2016 Aarboard AG, Andre Schild, www.aarboard.ch
 */
/**
 * Author:  a.schild
 * Created: 23.12.2015
 */

CREATE TABLE IF NOT EXISTS llx_swisspayments_factf (
rowid INT NOT NULL AUTO_INCREMENT ,
fk_factid INT NOT NULL,
esrline CHAR(80) NOT NULL,
esrpartynr CHAR(9) NOT NULL,
esrrefnr CHAR(27) NOT NULL,
PRIMARY KEY ( rowid )
) ENGINE = InnoDB ;


