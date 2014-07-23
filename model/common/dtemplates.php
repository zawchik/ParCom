<?php
class model_dtemplates extends model_base
{
    function get_data_templates($type = null, $id = null, $status = null, $name = null)
    {
        $conds = array();
        if ($status !== null)
            $conds[] = "status=" . $status;
        $query = "select * from csct_data_templates";
        if ($type)
            $conds[] = "dtmpl_type=" . $type;
        if ($id)
            $conds[] = "id=" . $id;
        if ($name)
            $conds[] = "(dtmpl_name like '%" . $name . "%' or id='" . $name . "' or dtmpl_cname like '%" . $name .
                "%')";
        if ($conds)
            $query .= " where " . join(" and ", $conds);
        if (!$id)
            return $this->dbh->queryFetchAll($query);
        else
            return $this->dbh->queryFetchRow($query);
    }

    function get_dtmpl_structure($id, $dfield_id = null, $assoc = true)
    {
        //выборка из справочников
        $field_id = 0;
        $parent_field = 0;
        $flib_query = "select * from csct_dtmpl_flib where field_id=:field_id";
        $flib_sql = $this->dbh->prepare($flib_query);
        $flib_sql->bindParam(':field_id', $field_id, PDO::PARAM_INT);
        //для зависимых выборок
        $pl_query = "select tp.*, df.ftype from csct_dtmpl_plib tp, csct_dtmpl_fields df where tp.field_id=:field_id and df.id=tp.parent_field";
        $pl_sql = $this->dbh->prepare($pl_query);
        $pl_sql->bindParam(':field_id', $field_id, PDO::PARAM_INT);

        $lfq = "select distinct tf.* from csct_dtmpl_fields tf where tf.ftype=2 and tf.dtmpl_id in (select distinct lb.dtmpl_id from csct_library lb where lb.id in (select distinct libs from csct_dtmpl_flib where field_id=:parent_field))";
        $lfsql2 = $this->dbh->prepare($lfq);
        $lfsql2->bindParam(':parent_field', $parent_field, PDO::PARAM_INT);
        $lfq = "select distinct tf.* from csct_dtmpl_fields tf where tf.ftype=2 and tf.dtmpl_id in (select distinct lb.dtmpl_id from csct_library lb where lb.id in (select distinct libs from csct_dtmpl_flib where field_id=(select distinct data_field from csct_dtmpl_plib where field_id=:parent_field)))";
        $lfsql6 = $this->dbh->prepare($lfq);
        $lfsql6->bindParam(':parent_field', $parent_field, PDO::PARAM_INT);

        $lquery = "select id, libs from csct_dtmpl_flib where field_id=:ffield_id";
        $lsql = $this->dbh->prepare($lquery);
        $lsql->bindParam(':ffield_id', $ffield_id, PDO::PARAM_INT);

        $dp_query = "select p.data_id id, p.header from csct_pages_text p where p.data_id in (select item_id from csct_dtmpl_fdp where field_id=:field_id and ptype=0) group by p.data_id";
        $dp_sql = $this->dbh->prepare($dp_query);
        $dp_sql->bindParam(':field_id', $field_id, PDO::PARAM_INT);

        $dplc_query = "select p.data_id id, p.header from csct_list_items_text p where p.data_id in (select item_id from csct_dtmpl_fdp where field_id=:field_id and ptype=1) group by p.data_id";
        $dplc_sql = $this->dbh->prepare($dplc_query);
        $dplc_sql->bindParam(':field_id', $field_id, PDO::PARAM_INT);

        $query = "select * from csct_data_templates where id=" . $id;
        $dtmpl_data = $this->dbh->queryFetchRow($query);

        $field_query = "select * from csct_dtmpl_fields where dtmpl_id='" . $id . "'";
        if ($dfield_id) {
            if (is_array($dfield_id))
                $field_query .= " and id in (" . join(", ", $dfield_id) . ")";
            else
                $field_query .= " and id=" . $dfield_id;
        }
        $field_query .= " order by num asc";
        $field_data = $this->dbh->queryFetchAll($field_query);
        $knum = 0;
        $field_list = array();
        foreach ($field_data as $key => $field) {
            $akey = $assoc ? $field['cname']:$knum;
            $knum++;
            $field_list[$akey] = $field;
            $field_id = $field['id'];
            if ($field['ftype'] == 2) {
                $flib_sql->execute();
                $flib_result = $flib_sql->fetch();
                $flib_sql->closeCursor();
                $field_list[$akey]['sel_libs'] = (count($flib_result) != 0) ? explode(",", $flib_result['libs']):
                    array();
                $ffield_id = $field_id;
                $lsql->execute();
                $field_list[$akey]['libs'] = $lsql->fetch();
                $lsql->closeCursor();
            }
            elseif ($field['ftype'] == 6) {
                $pl_sql->execute();
                $spfr = $pl_sql->fetch();
                $pl_sql->closeCursor();
                $ffield_id = $spfr['data_field'];
                $lsql->execute();
                $field_list[$akey]['libs'] = $lsql->fetch();
                $lsql->closeCursor();
                $field_list[$akey]['spfr'] = $spfr;
                $parent_field = $spfr['parent_field'];
                if ($spfr['ftype'] == 2) {
                    $lfsql2->execute();
                    $field_list[$akey]['lfr'] = $lfsql2->fetchAll();
                    $lfsql2->closeCursor();
                }
                else {
                    $lfsql6->execute();
                    $field_list[$akey]['lfr'] = $lfsql6->fetchAll();
                    $lfsql6->closeCursor();
                }
            }
            elseif ($field['ftype'] == 5)
                $field_list[$akey]['fsel'] = $this->get_fsel_data($field_id);
            elseif ($field['ftype'] == 8) {
                $field_value = array();
                $dp_sql->execute();
                $page_list = $dp_sql->fetchAll();
                $dp_sql->closeCursor();

                $dplc_sql->execute();
                $lc_list = $dplc_sql->fetchAll();
                $dplc_sql->closeCursor();
                foreach ($page_list as $page)
                    $field_value[] = array('id' => '0_' . $page['id'], 'header' => $page['header']);
                foreach ($lc_list as $page)
                    $field_value[] = array('id' => '1_' . $page['id'], 'header' => $page['header']);

                $field_list[$akey]['dp_list'] = $field_value;

            }

        }

        $dtmpl_data['fields'] = $field_list;
        $dtmpl_data['groups'] = $this->get_dtmpl_groups($id);
        return $dtmpl_data;
    }

    function get_dtmpl_groups($id, $get_structure = false)
    {
        $query = "select * from csct_dtmpl_groups where dtmpl_id=" . $id;
        $groups_predata = $this->dbh->queryFetchAll($query);
        $groups_data = array();
        foreach ($groups_predata as $group)
            $groups_data[$group['id']] = $group;
        if ($get_structure) {
            if ($groups_data) {
                foreach ($groups_data as $key => $group) {
                    $fields = explode(",", $group['fields']);
                    $groups_data[$key]['group_data'] = $this->get_dtmpl_structure($id, $fields);
                }
            }
        }
        return $groups_data;
    }

    function get_fsel_data($field_id)
    {
        $fsel_query = "select * from csct_dtmpl_fsel where field_id='" . $field_id . "'";
        return $this->dbh->queryFetchAll($fsel_query);
    }

    function get_libs($ch_status = false, $name = null)
    {
        $lib_query = "select mt.*, dt.dtmpl_name from csct_library mt left join csct_data_templates dt on mt.dtmpl_id=dt.id";
        if ($ch_status)
            $lib_query .= " where mt.status=1";
        if ($name)
            $query .= ($ch_status ? " and ":" where ") . " (mt.name like '%" . $name . "%' or mt.id='" . $name .
                "')";
        return $this->dbh->queryFetchAll($lib_query);
    }
    function get_dtmpl_fields($dtmpl_id, $ids)
    {
        $lfield_query = "select * from csct_dtmpl_fields where dtmpl_id=" . $dtmpl_id . " and ftype in (" .
            $ids . ")";
        return $this->dbh->queryFetchAll($lfield_query);
    }

    function del_dtemplate($id)
    {
        $query = "select * from csct_data_templates where id=" . $id;
        $dtmpl_data = $this->dbh->queryFetchRow($query);
        if ($dtmpl_data['dtmpl_type'] == 1)
            $query = "select count(id) from csct_pages where dtmpl=" . $id;
        elseif ($dtmpl_data['dtmpl_type'] == 2)
            $query = "select count(id) from csct_library where dtmpl_id=" . $id;
        elseif ($dtmpl_data['dtmpl_type'] == 3)
            $query = "select count(id) from csct_pages where dtmpl_id_lc=" . $id;
        elseif ($dtmpl_data['dtmpl_type'] == 4)
            $query = "select count(id) from csct_page_groups where dtmpl_id=" . $id;
        if (!current($this->dbh->queryFetchRow($query))) {
            $this->dbh->exec('delete from csct_data_templates where id=' . $id);
            $this->dbh->exec('delete from csct_dtmpl_fields where dtmpl=' . $id);
            $this->dbh->exec('delete from csct_dtmpl_groups where dtmpl_id=' . $id);
        }
    }

    function add_dtemplate()
    {
        $query = "insert into csct_data_templates (user_id, dtmpl_name, dtmpl_cname, dtmpl_type) values ('" .
            $this->registry->user_id . "', :dtmpl_name, :dtmpl_cname, :dtmpl_type)";
        $sql = $this->dbh->prepare($query);
        $sql->bindParam(':dtmpl_name', $_POST['dtmpl_name'], PDO::PARAM_STR);
        $sql->bindParam(':dtmpl_cname', $_POST['dtmpl_cname'], PDO::PARAM_STR);
        $sql->bindParam(':dtmpl_type', $_POST['dtmpl_type'], PDO::PARAM_INT);
        $sql->execute();
        $sql->closeCursor();
        return $this->dbh->lastInsertId();
    }

    function del_tf($field_id)
    {
        $query = "delete from csct_dtmpl_fields where id='" . $field_id . "'";
        $this->dbh->exec($query);
        $query = "delete from csct_tdata_fields where field_id='" . $field_id . "'";
        $this->dbh->exec($query);
        $query = "delete from csct_tdata_fsel where field_id='" . $field_id . "'";
        $this->dbh->exec($query);
        $query = "delete from csct_dtmpl_plib where field_id='" . $field_id . "'";
        $this->dbh->exec($query);
        $query = "delete from csct_tdata_flib where flib_id in (select id from csct_dtmpl_flib where field_id='" .
            $fid . "')";
        $this->dbh->exec($query);
        $query = "delete from csct_dtmpl_flib where field_id='" . $field_id . "'";
        $this->dbh->exec($query);
        $query = "delete from csct_dtmpl_fsel where field_id='" . $field_id . "'";
        $this->dbh->exec($query);
        $query = "delete from csct_dp_links where field_id='" . $field_id . "'";
        $this->dbh->exec($query);
        $query = "delete from csct_ds_links where field_id='" . $field_id . "'";
        $this->dbh->exec($query);

        //группы
        $query = "delete from csct_tgdata_fields where field_id='" . $field_id . "'";
        $this->dbh->exec($query);
        $query = "delete from csct_tgdata_fsel where field_id='" . $field_id . "'";
        $this->dbh->exec($query);
        $query = "delete from csct_tgdata_flib where flib_id in (select id from csct_dtmpl_flib where field_id='" .
            $fid . "')";
        $this->dbh->exec($query);
        $query = "delete from csct_dgp_links where field_id='" . $field_id . "'";
        $this->dbh->exec($query);
        $query = "delete from csct_dgs_links where field_id='" . $field_id . "'";
        $this->dbh->exec($query);

    }

    function add_tf()
    {
        $q = "select max(num) from csct_dtmpl_fields where dtmpl_id='" . $_REQUEST['dtmpl_id'] . "'";
        $count = current($this->dbh->query($q)->fetch()) + 1;
        $qry = "select * from csct_data_templates where id=" . $_REQUEST['dtmpl_id'];
        $dtmpl_data = $this->dbh->queryFetchRow($qry);

        $set_ml = isset($_REQUEST['n_is_ml']) ? 1:0;
        $set_html = isset($_REQUEST['n_is_html']) ? 1:0;

        $field_name = $_POST['nfvalue'];
        $field_cname = $_POST['nfcvalue'];
        $field_multi = isset($_POST['nfmvalue']) ? 1:0;
        $defval = isset($_POST['defval_' . $_POST['nftype']]) ? $_POST['defval_' . $_POST['nftype']]:'';
        $query = "insert into csct_dtmpl_fields values ('', '" . $_REQUEST['dtmpl_id'] . "', '" . $count .
            "', :field_name, :field_cname, :field_multi, '" . $_REQUEST['nftype'] . "', :defval, '" . $set_ml .
            "', '" . $set_html . "')";
        $sql = $this->dbh->prepare($query);
        $sql->bindParam(':field_name', $field_name, PDO::PARAM_STR);
        $sql->bindParam(':field_cname', $field_cname, PDO::PARAM_STR);
        $sql->bindParam(':field_multi', $field_multi, PDO::PARAM_INT);
        $sql->bindParam(':defval', $defval, PDO::PARAM_STR);
        $sql->execute();
        $field_id = $this->dbh->lastInsertId();
        $sql->closeCursor();

        if (!in_array($_POST['nftype'], array(
            2,
            6,
            8))) {
            if ($dtmpl_data['dtmpl_type'] == 1)
                $query = "select * from csct_pages where dtmpl_id=" . $_REQUEST['dtmpl_id'];
            elseif ($dtmpl_data['dtmpl_type'] == 2)
                $query = "select *, " . (app()->ml ? "1 use_ml":"0 use_ml") .
                    " from csct_lib_content where dtmpl_id=" . $_REQUEST['dtmpl_id'];
            elseif ($dtmpl_data['dtmpl_type'] == 3)
                $query = "select mt.*, pp.use_ml from `csct_list_items` mt left join csct_pages pp on mt.parent_id=pp.id where pp.dtmpl_id_lc=" .
                    $_REQUEST['dtmpl_id'];
            else
                $query = "select * from csct_page_groups where dtmpl_id=" . $_REQUEST['dtmpl_id'];
            $prc_id = $this->dbh->queryFetchAll($query);

            $data_id = 0;
            $lid = 0;
            $fvalue = '';
            $fnvalue = 0;
            $fdvalue = '';
            $query = "insert into csct_tdata_fields (data_id, field_id, lang_id, fvalue, fnvalue, fdvalue) values (:data_id, :field_id, :lang_id, :fvalue, :fnvalue, :fdvalue)";
            $isql = $this->dbh->prepare($query);
            $isql->bindParam(':data_id', $data_id, PDO::PARAM_INT);
            $isql->bindParam(':field_id', $field_id, PDO::PARAM_INT);
            $isql->bindParam(':lang_id', $lid, PDO::PARAM_INT);
            $isql->bindParam(':fvalue', $fvalue, PDO::PARAM_STR);
            $isql->bindParam(':fnvalue', $fnvalue, PDO::PARAM_INT);
            $isql->bindParam(':fdvalue', $fdvalue, PDO::PARAM_STR);

            foreach ($prc_id as $item) {
                $data_id = $item['id'];
                $fvalue = '';
                $fnvalue = 0;
                $fdvalue = '';
                $lid = (app()->ml && $set_ml && $item['use_ml']) ? app()->lang_main:0;
                if ($_POST['nftype'] == 0)
                    $fvalue = $defval;
                elseif ($_POST['nftype'] == 3 || $_POST['nftype'] == 4)
                    $fnvalue = $defval;
                elseif ($_POST['nftype'] == 7 && $defval) {
                    list($d, $m, $y) = explode(".", $defval);
                    $fdvalue = $y . '-' . $m . '-' . $d;
                }
                $isql->execute();
                $isql->closeCursor();
            }

        }

        if ($_POST['nftype'] == 6) {
            $query = "insert into csct_dtmpl_plib values ('', '" . $field_id . "', '" . $_POST['pf_' . $_POST['frnd']] .
                "', '" . $_POST['df_' . $_POST['frnd']] . "')";
            $this->dbh->exec($query);
        }
        if ($_POST['nftype'] == 8) {
            if (isset($_POST['nfdp']) && $_POST['nfdp']) {
                $plist = explode(",", $_POST['nfdp']);
                foreach ($plist as $page) {
                    list($ptype, $item_id) = explode("_", $page);
                    $this->dbh->exec("insert into csct_dtmpl_fdp values ('" . $field_id . "','" . $ptype . "','" . $item_id .
                        "')");
                }
            }
        }
        if ($_REQUEST['nftype'] == 2) {
            $libstr = "";
            $query = "select * from csct_library";
            foreach ($this->dbh->query($query) as $lrow) {
                $finame = "nflib_" . $lrow['id'];
                if (isset($_REQUEST[$finame]))
                    $libstr .= $lrow['id'] . ",";
            }
            $libstr = trim($libstr, ",");
            $query = "insert into csct_dtmpl_flib values ('', '" . $_REQUEST['dtmpl_id'] . "', '" . $field_id .
                "', '" . $libstr . "')";
            $this->dbh->exec($query);
        }
        if ($_REQUEST['nftype'] == 5 && isset($_SESSION['csct_dtmpl_fsel']) && !empty($_SESSION['csct_dtmpl_fsel'])) {
            foreach ($_SESSION['csct_dtmpl_fsel'] as $value) {
                $query = "insert into csct_dtmpl_fsel values ('', '" . $field_id . "', '" . $value['name'] . "','" .
                    $value['cname'] . "')";
                $this->dbh->exec($query);
            }
            unset($_SESSION['csct_dtmpl_fsel']);
        }
    }

    function spl()
    {
        if ($_POST['field_id'] != 0) {
            $query = "update csct_dtmpl_plib set parent_field='" . $_POST['parent_id'] .
                "', data_field='' where field_id='" . $_POST['field_id'] . "'";
            $this->dbh->exec($query);
        }
        $query = "select * from csct_dtmpl_fields where id=" . $_POST['parent_id'];
        $fres = $this->dbh->queryFetchRow($query);
        if ($fres['ftype'] == 2)
            $inq = "select distinct libs from csct_dtmpl_flib where field_id=" . $_POST['parent_id'];
        else
            $inq = "select distinct libs from csct_dtmpl_flib where field_id=(select data_field from csct_dtmpl_plib where field_id=" .
                $_POST['parent_id'] . ")";

        $lfq = "select distinct tf.* from csct_dtmpl_fields tf where tf.ftype=2 and tf.dtmpl_id in (select distinct lb.dtmpl_id from csct_library lb where lb.id in (" .
            $inq . "))";
        $lfr = $this->dbh->query($lfq)->fetchAll();
        if (!empty($lfr)) {
            echo "Выберите поле-источник данных:<br>\n";
            echo "<select name=df_" . $_POST['div_a'] . " size=1>\n";
            foreach ($lfr as $df)
                echo "<option value=\"" . $df['id'] . "\">" . $df['name'] . "</option>\n";
            echo "</select>\n";
        }
        else
            echo "Указанные шаблоны справочников не содержат привязок к справочникам.\n";
    }

    function del_sel()
    {
        $query = "delete from csct_dtmpl_fsel where id=" . $_POST['id'];
        $this->dbh->exec($query);
    }

    function add_fsel()
    {
        $query = "select * from csct_dtmpl_fsel where field_id=" . $_POST['field_id'] . " and name='" . $_POST['value'] .
            "' and cname='" . $_POST['cvalue'] . "'";
        $result = $this->dbh->queryFetchRow($query);
        if (!$result) {
            $query = "insert into csct_dtmpl_fsel values ('', :field_id, :value, :cvalue)";
            $sql = $this->dbh->prepare($query);
            $sql->bindParam(':field_id', $_POST['field_id'], PDO::PARAM_INT);
            $sql->bindParam(':value', $_POST['value'], PDO::PARAM_STR);
            $sql->bindParam(':cvalue', $_POST['cvalue'], PDO::PARAM_STR);
            $sql->execute();
            $id = $this->dbh->lastInsertId();
            $sql->closeCursor();
        }
        else
            $id = $result['id'];
        return $id;
    }

    function set_dval()
    {
        $query = "update csct_tdata_fields set " . $_POST['fname'] . "='" . $_POST['fval'] .
            "' where field_id='" . $_POST['field_id'] . "'";
        $count = $this->dbh->exec($query);
        $query = "update csct_dtmpl_fields set default_value='" . $_POST['fval'] . "' where id='" . $_POST['field_id'] .
            "'";
        $this->dbh->exec($query);
    }

    function upd_tsel()
    {
        //list($foo, $item_id) = explode("_", $_POST['id']);
        if ($_POST['data'] == 'value')
            $query = "update csct_dtmpl_fsel set name=:name where id=" . $_POST['pk'];
        else
            $query = "update csct_dtmpl_fsel set cname=:name where id=" . $_POST['pk'];
        $sql = $this->dbh->prepare($query);
        $sql->bindParam(':name', $_POST['value'], PDO::PARAM_STR);
        $sql->execute();
        $sql->closeCursor();
    }

    function dtmpl_process()
    {
        //Обновляем имя шаблона
        $query = "update csct_data_templates set dtmpl_name=:templ_name, dtmpl_cname=:templ_cname where id=:templ_id";
        $sql = $this->dbh->prepare($query);
        $sql->bindParam(':templ_name', $_POST['dtmplname'], PDO::PARAM_STR);
        $sql->bindParam(':templ_cname', $_POST['dtmplcname'], PDO::PARAM_STR);
        $sql->bindParam(':templ_id', $_POST['dtmpl_id'], PDO::PARAM_INT);
        $sql->execute();
        $sql->closeCursor();

        //Формируем запросы
        $field_id = $nlang = $olang = $field_type = $set_ml = $set_html = $fid = $data_field = $required = $stuff =
            $show_header = $fmulti = 0;
        $field_name = $libstr = $defval = $field_cname = "";
        //выдёргиваем данные поля
        $tf_query = "select * from csct_dtmpl_fields where id=:field_id";
        $tf_sql = $this->dbh->prepare($tf_query);
        $tf_sql->bindParam(':field_id', $field_id);
        //обновляем язык в таблице данных (поменять таблицу в базе с obj_fields!!!)
        $dfupd_query = "update csct_tdata_fields set lang_id=:nlang where lang_id=:olang and field_id=:field_id";
        $dfupd_sql = $this->dbh->prepare($dfupd_query);
        $dfupd_sql->bindParam(':nlang', $nlang);
        $dfupd_sql->bindParam(':olang', $olang);
        $dfupd_sql->bindParam(':field_id', $field_id);
        //обновляем данные поля
        $tfupd_query = "update csct_dtmpl_fields set name=:field_name, cname=:field_cname, multi=:fmulti, ftype=:field_type, is_ml=:is_ml, is_html=:is_html, default_value=:defval where id=:field_id";
        $tfupd_sql = $this->dbh->prepare($tfupd_query);
        $tfupd_sql->bindParam(':field_name', $field_name);
        $tfupd_sql->bindParam(':field_cname', $field_cname);
        $tfupd_sql->bindParam(':fmulti', $fmulti);
        $tfupd_sql->bindParam(':field_type', $field_type);
        $tfupd_sql->bindParam(':is_ml', $set_ml);
        $tfupd_sql->bindParam(':is_html', $set_html);
        $tfupd_sql->bindParam(':field_id', $field_id);
        $tfupd_sql->bindParam(':defval', $defval);
        //удаляем записи из таблицы данных
        $dd_query = "delete from csct_tdata_fields where field_id=:field_id";
        $dd_sql = $this->dbh->prepare($dd_query);
        $dd_sql->bindParam(':field_id', $field_id);
        //готовим массив справочников
        $query = "select * from csct_library";
        $user_libs = $this->dbh->query($query)->fetchAll();
        //проверяем наличие ссылки на библиотеки
        $tlc_query = "select count(id) from csct_dtmpl_flib where field_id=:field_id";
        $tlc_sql = $this->dbh->prepare($tlc_query);
        $tlc_sql->bindParam(':field_id', $field_id);
        //обновляем строку ссылок на библиотеки
        $tlupd_query = "update csct_dtmpl_flib set libs=:libstr where field_id=:field_id";
        $tlupd_sql = $this->dbh->prepare($tlupd_query);
        $tlupd_sql->bindParam(':libstr', $libstr);
        $tlupd_sql->bindParam(':field_id', $field_id);
        //создаем запись ссылок на библиотеки
        $tlins_query = "insert into csct_dtmpl_flib values ('', '" . $_POST['dtmpl_id'] .
            "', :field_id, :libstr)";
        $tlins_sql = $this->dbh->prepare($tlins_query);
        $tlins_sql->bindParam(':field_id', $field_id);
        $tlins_sql->bindParam(':libstr', $libstr);
        //удаляем данные при переключении на выборку из справочников
        $ddl_query = "delete from csct_tdata_flib where flib_id in (select id from csct_dtmpl_flib where field_id=:field_id)";
        $ddl_sql = $this->dbh->prepare($ddl_query);
        $ddl_sql->bindParam(':field_id', $field_id);
        $dtl_query = "delete from csct_dtmpl_flib where field_id=:field_id";
        $dtl_sql = $this->dbh->prepare($dtl_query);
        $dtl_sql->bindParam(':field_id', $field_id);
        $dll_query = "delete from csct_tdata_fields where field_id=:field_id";
        $dll_sql = $this->dbh->prepare($dll_query);
        $dll_sql->bindParam(':field_id', $field_id);
        //обновляем поле родительского шаблона
        $upl_query = "update csct_dtmpl_plib set data_field=:data_field where field_id=:field_id";
        $upl_sql = $this->dbh->prepare($upl_query);
        $upl_sql->bindParam(':field_id', $field_id);
        $upl_sql->bindParam(':data_field', $data_field);

        if (isset($_POST['field_id'])) {
            $is_ml = isset($_POST['is_ml']) ? $_POST['is_ml']:array();
            $is_html = isset($_POST['is_html']) ? $_POST['is_html']:array();

            for ($a = 0; $a < sizeof($_POST['field_id']); $a++) {
                $set_ml = isset($is_ml[$a]) ? 1:0;
                $set_html = isset($is_html[$a]) ? 1:0;

                $field_id = $_POST['field_id'][$a];
                $tf_sql->execute();
                $result = $tf_sql->fetch();
                $tf_sql->closeCursor();
                if ($result['is_ml'] != $set_ml) {
                    $olang = $set_ml ? 0:app()->lang_main;
                    $nlang = $set_ml ? app()->lang_main:0;
                    $dfupd_sql->execute();
                    $dfupd_sql->closeCursor();
                }
                $field_name = $_POST['fvalue'][$a];
                $field_cname = $_POST['fcvalue'][$a];
                $field_type = $_POST['ftype'][$a];
                $fmulti = isset($_POST['fmvalue'][$a]) ? 1:0;
                $defval = isset($_POST['defval_' . $field_type][$a]) ? $_POST['defval_' . $field_type][$a]:'';
                if ($defval && $field_type == 7) {
                    list($d, $m, $y) = explode(".", $defval);
                    $defval = mktime(0, 0, 0, $m, $d, $y);
                }
                $tfupd_sql->execute();
                $tfupd_sql->closeCursor();
                if ($field_type != $result['ftype']) {
                    $dd_sql->execute();
                    $dd_sql->closeCursor();
                }
                if ($field_type == 2) {
                    $libstr = "";
                    foreach ($user_libs as $lrow) {
                        $finame = "flib_" . $a . "_" . $lrow['id'];
                        if (isset($_POST[$finame]))
                            $libstr .= $lrow['id'] . ",";
                    }
                    reset($user_libs);
                    $libstr = trim($libstr, ",");
                    $tlc_sql->execute();
                    $tlc = current($tlc_sql->fetch());
                    $tlc_sql->closeCursor();
                    if ($tlc) {
                        $tlupd_sql->execute();
                        $tlupd_sql->closeCursor();
                    }
                    else {
                        $tlins_sql->execute();
                        $tlins_sql->closeCursor();
                    }
                }
                elseif ($field_type == 6) {
                    if (isset($_POST['df_' . $a])) {
                        $data_field = $_POST['df_' . $a];
                        $upl_sql->execute();
                        $upl_sql->closeCursor();
                    }

                }
                else {
                    $tlc_sql->execute();
                    $tlc = current($tlc_sql->fetch());
                    $tlc_sql->closeCursor();
                    if ($tlc) {
                        $ddl_sql->execute();
                        $ddl_sql->closeCursor();
                        $dtl_sql->execute();
                        $dtl_sql->closeCursor();
                        $dll_sql->execute();
                        $dll_sql->closeCursor();
                    }
                }
                if ($field_type == 8) {
                    $this->dbh->exec('delete from csct_dtmpl_fdp where field_id=' . $field_id);
                    if (isset($_POST['fdp'][$a]) && $_POST['fdp'][$a]) {
                        $plist = explode(",", $_POST['fdp'][$a]);
                        foreach ($plist as $page) {
                            list($ptype, $item_id) = explode("_", $page);
                            $this->dbh->exec("insert into csct_dtmpl_fdp values ('" . $field_id . "','" . $ptype . "','" . $item_id .
                                "')");
                        }
                    }
                }
            }
        }
        //Обрабатываем группы
        if (isset($_POST['tgroup_rnd']) && $_POST['tgroup_rnd']) {
            $name = '';
            $fields = '';
            $grid = 0;
            $query = "update csct_dtmpl_groups set name=:name, fields=:fields where id=:grid";
            $usql = $this->dbh->prepare($query);
            $usql->bindParam(':name', $name, PDO::PARAM_STR);
            $usql->bindParam(':fields', $fields, PDO::PARAM_STR);
            $usql->bindParam(':grid', $grid, PDO::PARAM_INT);
            $query = "insert into csct_dtmpl_groups (dtmpl_id, name, fields) values (:dtmpl_id, :name, :fields)";
            $isql = $this->dbh->prepare($query);
            $isql->bindParam(':name', $name, PDO::PARAM_STR);
            $isql->bindParam(':fields', $fields, PDO::PARAM_STR);
            $isql->bindParam(':dtmpl_id', $_POST['dtmpl_id'], PDO::PARAM_INT);
            foreach ($_POST['tgroup_rnd'] as $rnd) {
                $name = $_POST['fgr_name_' . $rnd];
                $fields = join(",", $_POST['dg_field_' . $rnd]);
                $grid = $_POST['tgroup_id_' . $rnd];
                if ($grid != 'new') {
                    $usql->execute();
                    $usql->closeCursor();
                }
                else {
                    $isql->execute();
                    $isql->closeCursor();
                }
            }
        }
    }

    function del_field()
    {
        $query = "delete from csct_tdata_fields where field_id=" . $_POST['field_id'];
        $this->dbh->exec($query);
        $query = "delete from csct_tdata_flib where field_id=" . $_POST['field_id'];
        $this->dbh->exec($query);
        $query = "delete from csct_dtmpl_fsel where field_id=" . $_POST['field_id'];
        $this->dbh->exec($query);
        $query = "delete from csct_dtmpl_flib where field_id=" . $_POST['field_id'];
        $this->dbh->exec($query);
        $query = "delete from csct_dp_links where field_id=" . $_POST['field_id'];
        $this->dbh->exec($query);
        $query = "delete from csct_ds_links where field_id=" . $_POST['field_id'];
        $this->dbh->exec($query);
        $query = "delete from csct_dtmpl_fields where id=" . $_POST['field_id'];
        $this->dbh->exec($query);

        $query = "delete from csct_tgdata_fields where field_id=" . $_POST['field_id'];
        $this->dbh->exec($query);
        $query = "delete from csct_tgdata_flib where field_id=" . $_POST['field_id'];
        $this->dbh->exec($query);
        $query = "delete from csct_dgp_links where field_id=" . $_POST['field_id'];
        $this->dbh->exec($query);
        $query = "delete from csct_dgs_links where field_id=" . $_POST['field_id'];
        $this->dbh->exec($query);

    }

    function del_fgroup()
    {
        $queries = array();
        $queries[] = "delete from csct_dtmpl_groups where id=" . $_POST['groupId'];
        $queries[] = "delete from csct_dgp_links where group_id=" . $_POST['groupId'];
        $queries[] = "delete from csct_dgs_links where group_id=" . $_POST['groupId'];
        $queries[] = "delete from csct_tgdata_fields where group_id=" . $_POST['groupId'];
        $queries[] = "delete from csct_tgdata_flib where group_id=" . $_POST['groupId'];
        foreach ($queries as $query)
            $this->dbh->exec($query);

    }

}
?>