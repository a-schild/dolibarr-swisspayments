#!/usr/bin/env bash
# Multi-company integration tests for the swisspayments module.
#
#   tests/multicompany/run.sh [version ...]     default: 15 24
#   tests/multicompany/run.sh down              remove all test containers
#
# For every version it starts a throw-away Dolibarr + MariaDB pair in Docker with this
# checkout mounted as htdocs/custom/swisspayments, simulates a second entity and runs
# fx.php (CLI) and ui.js (browser). See README.md.
set -u
HERE="$(cd "$(dirname "$0")" && pwd)"; REPO="$(cd "$HERE/../.." && pwd)"
declare -A IMAGE=([15]=dolibarr/dolibarr:15.0.3 [24]=dolibarr/dolibarr:24.0.2)
export NO_PROXY="localhost,127.0.0.1${NO_PROXY:+,$NO_PROXY}" no_proxy="localhost,127.0.0.1${no_proxy:+,$no_proxy}"
FX=/var/www/html/custom/swisspayments/tests/multicompany/fx.php

down() { for c in $(docker ps -aq --filter name=swp-); do docker rm -f "$c" >/dev/null; done; docker network rm swp-test >/dev/null 2>&1; }
if [ "${1:-}" = down ]; then down; exit 0; fi

rc=0
for v in "${@:-15 24}"; do for v in $v; do
  img="${SWP_IMAGE:-${IMAGE[$v]:-dolibarr/dolibarr:$v}}"
  echo "################ Dolibarr $v ($img)"
  docker rm -f swp-db$v swp-doli$v >/dev/null 2>&1; docker network create swp-test >/dev/null 2>&1
  docker run -d --name swp-db$v --network swp-test -e MARIADB_ROOT_PASSWORD=root -e MARIADB_DATABASE=dolibarr \
    -e MARIADB_USER=doli -e MARIADB_PASSWORD=doli mariadb:10.6 >/dev/null || { rc=1; continue; }
  docker run -d --name swp-doli$v --network swp-test -p 80$v:80 \
    -e DOLI_DB_HOST=swp-db$v -e DOLI_DB_USER=doli -e DOLI_DB_PASSWORD=doli -e DOLI_DB_NAME=dolibarr \
    -e DOLI_ADMIN_LOGIN=admin -e DOLI_ADMIN_PASSWORD=admin123 -e DOLI_URL_ROOT=http://localhost:80$v \
    -e DOLI_INSTALL_AUTO=1 -e DOLI_INIT_DEMO=0 -e DOLI_ENABLE_MODULES=Societe,Fournisseur,Banque,Facture \
    -v "$REPO":/var/www/html/custom/swisspayments:ro "$img" >/dev/null || { rc=1; continue; }
  echo "waiting for the Dolibarr install ..."
  for i in $(seq 1 90); do
    [ "$(curl -s -o /dev/null -w '%{http_code}' http://localhost:80$v/index.php)" = 200 ] && \
      docker exec swp-db$v mariadb -N -udoli -pdoli dolibarr -e "select 1 from llx_const where name='MAIN_MODULE_BANQUE'" 2>/dev/null | grep -q 1 && break
    sleep 4
  done
  db() { docker exec swp-db$v mariadb -udoli -pdoli dolibarr -e "$1"; }
  fx() { docker exec -u www-data swp-doli$v php $FX "$@" 2>&1 | grep -v '^$'; return ${PIPESTATUS[0]}; }

  # --- simulate multicompany: stub with third parties + bank accounts shared between
  #     entities 1 and 2, and an entity 2 with the same modules, dictionaries and rights
  docker exec swp-doli$v mkdir -p /var/www/html/custom/multicompany/class
  docker cp -q "$HERE/stub/actions_multicompany.class.php" swp-doli$v:/var/www/html/custom/multicompany/class/
  db "insert into llx_const(name,entity,value,type,visible) values('MAIN_MODULE_MULTICOMPANY',0,'1','chaine',0);
      insert into llx_const(name,entity,value,type,visible) select name,2,value,type,visible from llx_const
        where entity=1 and (name like 'MAIN_MODULE_%' or name like 'MAIN_INFO%' or name like 'MAIN_MONNAIE%' or name like '%ADDON%');
      insert into llx_c_paiement(entity,code,libelle,type,active) select 2,code,libelle,type,active from llx_c_paiement where entity=1;
      insert ignore into llx_rights_def(id,libelle,module,entity,perms,subperms,type,bydefault) select id,libelle,module,2,perms,subperms,type,bydefault from llx_rights_def where entity=1;
      insert ignore into llx_user_rights(entity,fk_user,fk_id) select 2,fk_user,fk_id from llx_user_rights where entity=1;" || rc=1

  echo "--- module activation";               fx 1 init || rc=1; fx 2 init || rc=1
  echo "--- fixtures";                        fx 1 fixture || rc=1; fx 2 fixture || rc=1
  echo "--- payment file in entity 2 (CLI)";  fx 2 pay || rc=1
  echo "--- isolation seen from entity 1";    fx 1 isolation 2 || rc=1
  echo "--- upgrade from the pre-2026.11.3 state"; fx 1 legacy || rc=1; fx 1 migrate || rc=1
  echo "--- browser";                         (cd "$HERE" && node ui.js $v) || rc=1
done; done
[ $rc = 0 ] && echo "ALL PASSED" || echo "FAILURES - see above"
[ -n "${SWP_KEEP:-}" ] || down
exit $rc
