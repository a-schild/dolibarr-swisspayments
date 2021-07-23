/* Swiss payments from ESR to DTA
 * Copyright (C) 2016 Aarboard AG, Andre Schild, www.aarboard.ch
 */
/**
 * Author:  a.schild
 * Created: 23.12.2015
 */

ALTER TABLE llx_swisspayments_factf ADD FOREIGN KEY (fk_factid) REFERENCES llx_facture_fourn(rowid) ON DELETE CASCADE;

