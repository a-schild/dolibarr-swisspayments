-- Swiss payments from ESR to DTA
-- Copyright (C) 2016 Aarboard AG, Andre Schild, www.aarboard.ch
--
-- You should have received a copy of the GNU General Public License
-- along with this program.  If not, see <http://www.gnu.org/licenses/>.
ALTER TABLE llx_swisspayments_soc ADD FOREIGN KEY (fk_societe) REFERENCES llx_societe(rowid) ON DELETE CASCADE;