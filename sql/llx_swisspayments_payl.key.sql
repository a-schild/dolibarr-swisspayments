/* Swiss payments from ESR to DTA
 * Copyright (C) 2016 Aarboard AG, Andre Schild, www.aarboard.ch
 */
/**
 * Author:  a.schild
 * Created: 05.01.2016
 */

ALTER TABLE llx_swisspayments_payl ADD FOREIGN KEY (fk_payh) REFERENCES llx_swisspayments_payh(rowid) ON DELETE CASCADE;
ALTER TABLE llx_swisspayments_payl ADD FOREIGN KEY (fk_payementfourn) REFERENCES llx_paiementfourn(rowid) ON DELETE CASCADE;


