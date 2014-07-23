<?php
class model_components extends model_base
{
    function get_snippet_data($snp_id, $get_text = true)
    {
        $query = "select * from csct_snippets where id=" . $snp_id;
        $page_data = $this->dbh->query($query)->fetch();
        if ($get_text) {
            $query = "select * from csct_snippets_text where data_id=" . $snp_id;
            $text_data = array();
            $result = $this->dbh->queryFetchAll($query);
            foreach ($result as $item)
                $text_data[$item['lang_id']] = $item;
            $page_data['text'] = $text_data;
        }
        return $page_data;
    }

    function get_module_list()
    {
        $query = "select * from csct_modules";
        $result = $this->dbh->queryFetchAll($query);
        return $result;
    }

    function get_module_data($mdl_id)
    {
        $query = "select * from csct_modules where id=" . $mdl_id;
        $mdl_data = $this->dbh->queryFetchRow($query);
        $query = "select * from csct_modules_ctrl where mdl_id=" . $mdl_id;
        $mdl_data['ctrls'] = $this->dbh->queryFetchAll($query);
        $query = "select * from csct_modules_tmpl where mdl_id=" . $mdl_id;
        $mdl_data['tmpls'] = $this->dbh->queryFetchAll($query);
        return $mdl_data;
    }

    function get_module_ctrl_data($ctrl_id)
    {
        $query = "select * from csct_modules_ctrl where id=" . $ctrl_id;
        return $this->dbh->queryFetchRow($query);
    }

    function get_module_addrqty($ctrl_id, $ctrl_addr)
    {
        $query = "select count(*) from csct_pages where address='" . $ctrl_addr . "'";
        $addrqty = current($this->dbh->queryFetchRow($query));
        $query = "select count(*) from csct_modules_ctrl where id<>" . $ctrl_id . " and ctrl_name='" . $ctrl_addr .
            "'";
        $addrqty += current($this->dbh->queryFetchRow($query));
        return $addrqty;
    }

    function get_module_tmpl_data($tmpl_id)
    {
        $query = "select * from csct_modules_tmpl where id=" . $tmpl_id;
        return $this->dbh->queryFetchRow($query);
    }

    function get_tmpl_data($tmpl_id, $get_text = true)
    {
        $query = "select * from csct_templates where id=" . $tmpl_id;
        $page_data = $this->dbh->queryFetchRow($query);
        if ($get_text) {
            $query = "select * from csct_templates_text where data_id=" . $tmpl_id;
            $text_data = array();
            $result = $this->dbh->queryFetchAll($query);
            foreach ($result as $item)
                $text_data[$item['lang_id']] = $item;
            $page_data['text'] = $text_data;
        }
        return $page_data;
    }

    function get_stmpl_data($stmpl_id, $parent_id = null)
    {
        $pid = 0;
        $query = "select count(id) from csct_stmpl_data where parent=:pid";
        $sql = $this->dbh->prepare($query);
        $sql->bindParam(':pid', $pid, PDO::PARAM_INT);

        $query = "select * from csct_stmpl_data where stmpl_id=" . $stmpl_id;
        if ($parent_id !== null)
            $query .= " and parent=" . $parent_id;
        $query .= " order by num asc";
        $stmpl_data = $this->dbh->queryFetchAll($query);
        foreach ($stmpl_data as $key => $item) {
            $pid = $item['id'];
            $sql->execute();
            $stmpl_data[$key]['children'] = current($sql->fetch());
            $sql->closeCursor();
        }
        return $stmpl_data;
    }

    function get_stmpl_div_data($stmpl_div_id)
    {
        $query = "select * from csct_stmpl_data where id=" . $stmpl_div_id;
        return $this->dbh->queryFetchRow($query);
    }

    function del_snippet($id)
    {
        $query = "delete from csct_snippets where id=" . $id;
        $this->dbh->exec($query);
        $query = "delete from csct_snippets_text where data_id=" . $id;
        $this->dbh->exec($query);
    }

    function add_snippet()
    {
        if ($_POST['address'] && $_POST['header']) {
            $query = "insert into csct_snippets (user_id, snp_name, snp_cname, stype, use_ml, status) values ('" .
                $this->registry['user_id'] . "', :snp_name, :snp_cname, '" . $_POST['stype'] . "', '" . ((isset($_POST['use_ml']) &&
                $_POST['use_ml']) ? 1:0) . "', '1')";
            $sql = $this->dbh->prepare($query);
            $sql->bindParam(':snp_name', $_POST['header'], PDO::PARAM_STR);
            $sql->bindParam(':snp_cname', $_POST['address'], PDO::PARAM_STR);
            $sql->execute();
            $sql->closeCursor();
            $snp_id = $this->dbh->lastInsertId();

            $lid = (isset($_POST['use_ml']) && $_POST['use_ml']) ? app()->lang_main:0;
            $query = "insert into csct_snippets_text (id, data_id, lang_id, content) values ('', '" . $snp_id .
                "', '" . $lid . "', '')";
            $sql = $this->dbh->exec($query);
            return $snp_id;
        }
    }

    function process_snippet()
    {
        $page_result = $this->get_snippet_data($_POST['snp_id'], false);
        $query = "update csct_snippets set snp_name=:snp_name, snp_cname=:snp_cname, stype=:stype, use_ml=" . ((isset
            ($_POST['use_ml']) && $_POST['use_ml']) ? 1:0) . " where id=" . $_POST['snp_id'];
        $sql = $this->dbh->prepare($query);
        $sql->bindParam(':snp_name', $_POST['header'], PDO::PARAM_STR);
        $sql->bindParam(':snp_cname', $_POST['address'], PDO::PARAM_STR);
        $sql->bindParam(':stype', $_POST['stype'], PDO::PARAM_STR);
        $sql->execute();
        $sql->closeCursor();
        $lid = 0;
        $content = '';
        $query = "select count(id) from csct_snippets_text where data_id=" . $_POST['snp_id'] .
            " and lang_id=:lid";
        $isset_sql = $this->dbh->prepare($query);
        $isset_sql->bindParam(':lid', $lid, PDO::PARAM_INT);
        $query = "update csct_snippets_text set content=:content where data_id=" . $_POST['snp_id'] .
            " and lang_id=:lid";
        $upd_sql = $this->dbh->prepare($query);
        $upd_sql->bindParam(':lid', $lid, PDO::PARAM_INT);
        $upd_sql->bindParam(':content', $content, PDO::PARAM_STR);
        $query = "insert into csct_snippets_text values ('', '" . $_POST['snp_id'] . "', :lid, :content)";
        $ins_sql = $this->dbh->prepare($query);
        $ins_sql->bindParam(':lid', $lid, PDO::PARAM_INT);
        $ins_sql->bindParam(':content', $content, PDO::PARAM_STR);
        if ($page_result['use_ml'] && app()->ml) {
            foreach (app()->csct_langs as $lid => $lang_name) {
                $isset_sql->execute();
                $isset_lid = current($isset_sql->fetch());
                $isset_sql->closeCursor();
                $content = trim($_POST['snp_cnt_' . $lid]);
                $content = preg_replace('/^<\?php/iU', '', $content);
                $content = preg_replace('/^<\?/iU', '', $content);
                $content = preg_replace('/\?>$/iU', '', $content);
                $content = trim($content);
                if ($isset_lid) {
                    $upd_sql->execute();
                    $upd_sql->closeCursor();
                }
                else {
                    $ins_sql->execute();
                    $ins_sql->closeCursor();
                }
            }
        }
        else {
            $lid = 0;
            $isset_sql->execute();
            $isset_lid = current($isset_sql->fetch());
            $isset_sql->closeCursor();
            $content = trim($_POST['snp_cnt']);
            $content = preg_replace('/^<\?php/iU', '', $content);
            $content = preg_replace('/^<\?/iU', '', $content);
            $content = preg_replace('/\?>$/iU', '', $content);
            $content = trim($content);
            if ($isset_lid) {
                $upd_sql->execute();
                $upd_sql->closeCursor();
            }
            else {
                $ins_sql->execute();
                $ins_sql->closeCursor();
            }
        }
        if (app()->ml) {
            $use_ml = (isset($_POST['use_ml']) && $_POST['use_ml']) ? 1:0;
            if ($page_result['use_ml'] != $use_ml) {
                if ($use_ml)
                    $qry = "update csct_snippets_text set lang_id=" . app()->lang_main . " where data_id=" . $_POST['snp_id'];
                else
                    $qry = "update csct_snippets_text set lang_id=0 where data_id=" . $_POST['snp_id'] . " and lang_id=" .
                        app()->lang_main;
                $this->dbh->exec($qry);
            }
        }
    }

    function del_template($id)
    {
        $query = "delete from csct_templates where id=" . $id;
        $this->dbh->exec($query);
        $query = "delete from csct_templates_text where data_id=" . $id;
        $this->dbh->exec($query);
    }

    function add_template()
    {
        if ($_POST['address'] && $_POST['header']) {
            $query = "insert into csct_templates (user_id, tmpl_name, tmpl_cname, use_ml, status) values ('" . $this->
                registry['user_id'] . "', :tmpl_name, :tmpl_cname, '" . ((isset($_POST['use_ml']) && $_POST['use_ml']) ?
                1:0) . "', '1')";
            $sql = $this->dbh->prepare($query);
            $sql->bindParam(':tmpl_name', $_POST['header'], PDO::PARAM_STR);
            $sql->bindParam(':tmpl_cname', $_POST['address'], PDO::PARAM_STR);
            $sql->execute();
            $sql->closeCursor();
            $tmpl_id = $this->dbh->lastInsertId();
            $lid = (isset($_POST['use_ml']) && $_POST['use_ml']) ? app()->lang_main:0;
            $query = "insert into csct_templates_text (id, data_id, lang_id, content) values ('', '" . $tmpl_id .
                "', '" . $lid . "', '')";
            $sql = $this->dbh->exec($query);
            return $tmpl_id;
        }
    }

    function tmpl_dbl()
    {
        $tmpl_data = $this->get_tmpl_data($_POST['tmpl_id']);
        $query = "insert into csct_templates (user_id, tmpl_name, tmpl_cname, use_ml, status) values ('" . $this->
            registry['user_id'] . "', :tmpl_name, :tmpl_cname, '" . $tmpl_data['use_ml'] . "', '1')";
        $sql = $this->dbh->prepare($query);
        $name = "Копия " . $tmpl_data['tmpl_name'];
        $cname = "copy_" . $tmpl_data['tmpl_cname'];
        $sql->bindParam(':tmpl_name', $name, PDO::PARAM_STR);
        $sql->bindParam(':tmpl_cname', $cname, PDO::PARAM_STR);
        $sql->execute();
        $sql->closeCursor();
        $tmpl_id = $this->dbh->lastInsertId();
        $query = "insert into csct_templates_text (data_id, lang_id, content) values ('" . $tmpl_id .
            "', :lid, :content)";
        $sql = $this->dbh->prepare($query);
        $lid = 0;
        $content = '';
        $sql->bindParam(':lid', $lid, PDO::PARAM_INT);
        $sql->bindParam(':content', $content, PDO::PARAM_STR);
        foreach ($tmpl_data['text'] as $lid => $data) {
            $content = $data['content'];
            $sql->execute();
            $sql->closeCursor();
        }
        return $tmpl_id;
    }

    function process_template()
    {
        $page_result = $this->get_tmpl_data($_POST['tmpl_id'], false);
        $query = "update csct_templates set tmpl_name=:tmpl_name, tmpl_cname=:tmpl_cname, use_ml=" . ((isset
            ($_POST['use_ml']) && $_POST['use_ml']) ? 1:0) . " where id=" . $_POST['tmpl_id'];
        $sql = $this->dbh->prepare($query);
        $sql->bindParam(':tmpl_name', $_POST['header'], PDO::PARAM_STR);
        $sql->bindParam(':tmpl_cname', $_POST['address'], PDO::PARAM_STR);
        $sql->execute();
        $sql->closeCursor();
        $lid = 0;
        $content = '';
        $query = "select count(id) from csct_templates_text where data_id=" . $_POST['tmpl_id'] .
            " and lang_id=:lid";
        $isset_sql = $this->dbh->prepare($query);
        $isset_sql->bindParam(':lid', $lid, PDO::PARAM_INT);
        $query = "update csct_templates_text set content=:content where data_id=" . $_POST['tmpl_id'] .
            " and lang_id=:lid";
        $upd_sql = $this->dbh->prepare($query);
        $upd_sql->bindParam(':lid', $lid, PDO::PARAM_INT);
        $upd_sql->bindParam(':content', $content, PDO::PARAM_STR);
        $query = "insert into csct_templates_text values ('', '" . $_POST['tmpl_id'] . "', :lid, :content)";
        $ins_sql = $this->dbh->prepare($query);
        $ins_sql->bindParam(':lid', $lid, PDO::PARAM_INT);
        $ins_sql->bindParam(':content', $content, PDO::PARAM_STR);
        if ($page_result['use_ml'] && app()->ml) {
            foreach (app()->csct_langs as $lid => $lang_name) {
                $isset_sql->execute();
                $isset_lid = current($isset_sql->fetch());
                $isset_sql->closeCursor();
                $content = $_POST['tmpl_cnt_' . $lid];
                if ($isset_lid) {
                    $upd_sql->execute();
                    $upd_sql->closeCursor();
                }
                else {
                    $ins_sql->execute();
                    $ins_sql->closeCursor();
                }
            }
        }
        else {
            $lid = 0;
            $isset_sql->execute();
            $isset_lid = current($isset_sql->fetch());
            $isset_sql->closeCursor();
            $content = $_POST['tmpl_cnt'];
            if ($isset_lid) {
                $upd_sql->execute();
                $upd_sql->closeCursor();
            }
            else {
                $ins_sql->execute();
                $ins_sql->closeCursor();
            }
        }
        if (app()->ml) {
            $use_ml = (isset($_POST['use_ml']) && $_POST['use_ml']) ? 1:0;
            if ($page_result['use_ml'] != $use_ml) {
                if ($use_ml)
                    $qry = "update csct_templates_text set lang_id=" . app()->lang_main . " where data_id=" . $_POST['tmpl_id'];
                else
                    $qry = "update csct_templates_text set lang_id=0 where data_id=" . $_POST['tmpl_id'] .
                        " and lang_id=" . app()->lang_main;
                $this->dbh->exec($qry);
            }
        }
    }

    function del_module($id)
    {
        $query = "delete from csct_modules where id=" . $id;
        $this->dbh->exec($query);
        $query = "delete from csct_modules_ctrl where mdl_id=" . $id;
        $this->dbh->exec($query);
        $query = "delete from csct_modules_tmpl where mdl_id=" . $id;
        $this->dbh->exec($query);
    }

    function add_module()
    {
        if ($_POST['address'] && $_POST['header']) {
            $mdl_name = $_POST['header'];
            $mdl_cname = $_POST['address'];
            $query = "insert into csct_modules (user_id, mdl_name, mdl_cname, status) values ('" . $this->
                registry['user_id'] . "', :mdl_name, :mdl_cname, '1')";
            $sql = $this->dbh->prepare($query);
            $sql->bindParam(':mdl_name', $_POST['header'], PDO::PARAM_STR);
            $sql->bindParam(':mdl_cname', $_POST['address'], PDO::PARAM_STR);
            $sql->execute();
            $sql->closeCursor();
            $mdl_id = $this->dbh->lastInsertId();
            return $mdl_id;
        }
    }

    function del_ctrl($id)
    {
        $query = "delete from csct_modules_ctrl where mdl_id=" . $id;
        $this->dbh->exec($query);
    }

    function del_tmpl($id)
    {
        $query = "delete from csct_modules_tmpl where mdl_id=" . $id;
        $this->dbh->exec($query);
    }

    function del_lc($id)
    {
        $query = "select * from csct_library where id=(select ref_id from csct_lib_content where id=" . $id .
            ")";
        $lib_data = $this->dbh->queryFetchRow($query);
        $queries = array();
        $queries[] = "delete from csct_lib_content where id=" . $id;
        $queries[] = "delete from csct_lib_content_names where data_id=" . $id;
        if ($lib_data['dtmpl_id']) {
            $queries[] = "delete from csct_tdata_fields where field_id in (select id from csct_dtmpl_fields where dtmpl_id=" .
                $lib_data['dtmpl_id'] . ") and data_id=" . $id;
            $queries[] = "delete from csct_tdata_flib where flib_id in (select id from csct_dtmpl_fields where dtmpl_id=" .
                $lib_data['dtmpl_id'] . ") and data_id=" . $id;
            $queries[] = "delete from csct_dp_links where flib_id in (select id from csct_dtmpl_fields where dtmpl_id=" .
                $lib_data['dtmpl_id'] . ") and data_id=" . $id;
        }
        if ($lib_data['site_users'])
            $queries[] = "delete from site_users where lc_id=" . $id;
        foreach ($queries as $query)
            $this->dbh->exec($query);
    }

    function process_module()
    {
        $query = "update csct_modules set mdl_name=:mdl_name, mdl_cname=:mdl_cname where id=" . $_POST['mdl_id'];
        $sql = $this->dbh->prepare($query);
        $sql->bindParam(':mdl_name', $_POST['name'], PDO::PARAM_STR);
        $sql->bindParam(':mdl_cname', $_POST['cname'], PDO::PARAM_STR);
        $sql->execute();
        $sql->closeCursor();
    }

    function process_mdl_ctrl()
    {
        if (isset($_POST['ctrl_id']) && is_numeric($_POST['ctrl_id'])) {
            if ($_POST['ctrl_id'] == 0)
                $query = "insert into csct_modules_ctrl values ('', '" . $_POST['mdl_id'] .
                    "', :template, :name, :content, '1')";
            else
                $query = "update csct_modules_ctrl set template=:template, ctrl_name=:name, ctrl_content=:content where id=" .
                    $_POST['ctrl_id'];
            $sql = $this->dbh->prepare($query);
            $mdl_cnt = trim($_POST['mdl_cnt']);
            $mdl_cnt = preg_replace('/^<\?php/iU', '', $mdl_cnt);
            $mdl_cnt = preg_replace('/^<\?/iU', '', $mdl_cnt);
            $mdl_cnt = preg_replace('/\?>$/iU', '', $mdl_cnt);
            $mdl_cnt = trim($mdl_cnt);
            $sql->bindParam(':name', $this->trlit($_POST['address']), PDO::PARAM_STR);
            $sql->bindParam(':content', $mdl_cnt, PDO::PARAM_STR);
            $sql->bindParam(':template', $_POST['template'], PDO::PARAM_INT);
            $sql->execute();
            if ($_POST['ctrl_id'] == 0)
                $ctrl_id = $this->dbh->lastInsertId();
            else
                $ctrl_id = $_POST['ctrl_id'];
            return $ctrl_id;
        }
        else
            return null;
    }

    function process_mdl_tmpl()
    {
        if (isset($_POST['tmpl_id']) && is_numeric($_POST['tmpl_id'])) {
            if ($_POST['tmpl_id'] == 0)
                $query = "insert into csct_modules_tmpl values ('', '" . $_POST['mdl_id'] .
                    "', :name, :content, '1')";
            else
                $query = "update csct_modules_tmpl set tmpl_name=:name, tmpl_content=:content where id=" . $_POST['tmpl_id'];
            $sql = $this->dbh->prepare($query);
            $mdl_cnt = trim($_POST['mdl_cnt']);
            $mdl_cnt = preg_replace('/^<\?php/iU', '', $mdl_cnt);
            $mdl_cnt = preg_replace('/^<\?/iU', '', $mdl_cnt);
            $mdl_cnt = preg_replace('/\?>$/iU', '', $mdl_cnt);
            $mdl_cnt = trim($mdl_cnt);
            $sql->bindParam(':name', $this->trlit($_POST['address']), PDO::PARAM_STR);
            $sql->bindParam(':content', $mdl_cnt, PDO::PARAM_STR);
            $sql->execute();
            if ($_POST['tmpl_id'] == 0)
                $tmpl_id = $this->dbh->lastInsertId();
            else
                $tmpl_id = $_POST['tmpl_id'];
            return $tmpl_id;
        }
        else
            return null;
    }

    function del_subtemplate($id)
    {
        $query = "delete from csct_subtemplates where id=" . $id;
        $this->dbh->exec($query);
        $query = "delete from csct_stmpl_data_text where data_id in (select id from csct_stmpl_data where stmpl_id=" .
            $id . ")";
        $this->dbh->exec($query);
        $query = "delete from csct_stmpl_data where stmpl_id=" . $id;
        $this->dbh->exec($query);
    }

    function add_subtemplate()
    {
        if ($_POST['address'] && $_POST['header']) {
            $query = "insert into csct_subtemplates (user_id, stmpl_name, stmpl_cname, status) values ('" . $this->
                registry['user_id'] . "', :stmpl_name, :stmpl_cname, '1')";
            $sql = $this->dbh->prepare($query);
            $sql->bindParam(':stmpl_name', $_POST['header'], PDO::PARAM_STR);
            $sql->bindParam(':stmpl_cname', $_POST['address'], PDO::PARAM_STR);
            $sql->execute();
            $sql->closeCursor();
            $stmpl_id = $this->dbh->lastInsertId();
            return $stmpl_id;
        }
    }

    function stmpl_add_div()
    {
        $query = "select max(num) from csct_stmpl_data where stmpl_id=" . $_POST['stmpl_id'] .
            " and parent=" . $_POST['parent'];
        $mnum = $this->dbh->query($query)->fetch();
        $num = $mnum ? current($mnum) + 1:1;
        $query = "insert into csct_stmpl_data (stmpl_id, num, parent) values ('" . $_POST['stmpl_id'] .
            "', '" . $num . "', '" . $_POST['parent'] . "')";
        $this->dbh->exec($query);
        return json_encode(array('pid' => $this->dbh->lastInsertId()));
    }

    function stmpl_div_save_sort()
    {
        $num = 1;
        $id = 0;
        $query = "update csct_stmpl_data set num=:num where id=:id";
        $sql = $this->dbh->prepare($query);
        $sql->bindParam(':num', $num, PDO::PARAM_INT);
        $sql->bindParam(':id', $id, PDO::PARAM_INT);
        $order_list = explode(",", $_POST['order']);
        foreach ($order_list as $item) {
            list($foo, $id) = explode("_", trim($item));
            $sql->execute();
            $sql->closeCursor();
            $num++;
        }
    }

    function stmpl_div_process()
    {
        $query = "update csct_stmpl_data set div_style=:div_style, div_class=:div_class, div_in_style=:div_in_style, div_type=:div_type, use_html=:use_html, snp_id=:snp_id, default_text=:default_text where id=" .
            $_POST['stmpl_div'];
        $sql = $this->dbh->prepare($query);
        $use_html = isset($_POST['use_html']) ? 1:0;
        $sql->bindParam(':div_style', $_POST['div_style'], PDO::PARAM_STR);
        $sql->bindParam(':div_class', $_POST['div_class'], PDO::PARAM_STR);
        $sql->bindParam(':div_in_style', $_POST['div_in_style'], PDO::PARAM_STR);
        $sql->bindParam(':div_type', $_POST['div_type'], PDO::PARAM_INT);
        $sql->bindParam(':use_html', $use_html, PDO::PARAM_INT);
        $sql->bindParam(':snp_id', $_POST['snp_id'], PDO::PARAM_INT);
        $sql->bindParam(':default_text', $_POST['default_text'], PDO::PARAM_STR);
        $sql->execute();
        $sql->closeCursor();
    }

    function stmpl_div_delete($id)
    {
        $query = "delete from csct_stmpl_data where id=" . $id;
        $this->dbh->exec($query);
        $query = "delete from csct_stmpl_data_content where pid=" . $id;
        $this->dbh->exec($query);
    }

    function get_stmpl_photo_list($tdata_id, $page_type, $div_id)
    {
        $query = "select * from csct_stmpl_photos where page_id=" . $tdata_id . " and page_type=" . $page_type .
            " and block_id=" . $div_id;
        return $this->dbh->queryFetchAll($query);
    }

    function stmpl_gallery_img_upload()
    {

        $this->registry['no_tidy'] = true;
        if (!empty($_FILES)) {
            $idir_base = $_SERVER['DOCUMENT_ROOT'] . "/uploads/upimg/phg_images/";
            $thdir_base = $_SERVER['DOCUMENT_ROOT'] . "/uploads/upimg/phg_thumbnails/";
            $tempFile = $_FILES['Filedata']['tmp_name'];
            $filename = time() . "_" . $this->trlit($_FILES['Filedata']['name']) . '.jpg';
            $improp = getimagesize($tempFile);
            $width = $improp[0];
            $height = $improp[1];

            if ($width > 2000 || $height > 2000)
                die();

            if ($width > $height) {
                $fsscale = 1200 / $width;
                $thscale = 75 / $height;
            }
            else {
                $fsscale = 1200 / $height;
                $thscale = 75 / $width;
            }
            $fsnw = round($width * $fsscale);
            $fsnh = round($height * $fsscale);
            $thnw = round($width * $thscale);
            $thnh = round($height * $thscale);

            $srcim = imagecreatefromjpeg($tempFile);
            $dstim = imagecreatetruecolor($fsnw, $fsnh);
            $newim = imagecopyresampled($dstim, $srcim, 0, 0, 0, 0, $fsnw, $fsnh, $width, $height);
            if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/img/parcom/watermark.png')) {
                $watermarkfile_id = imagecreatefrompng($_SERVER['DOCUMENT_ROOT'] . '/img/parcom/watermark.png');
                imageAlphaBlending($watermarkfile_id, false);
                imageSaveAlpha($watermarkfile_id, true);
                $sourcefile_width = imageSX($dstim);
                $sourcefile_height = imageSY($dstim);
                $watermarkfile_width = imageSX($watermarkfile_id);
                $watermarkfile_height = imageSY($watermarkfile_id);

                $dest_x = ($sourcefile_width) - ($watermarkfile_width);
                $dest_y = ($sourcefile_height) - ($watermarkfile_height);
                imagecopy($dstim, $watermarkfile_id, $dest_x, $dest_y, 0, 0, $watermarkfile_width, $watermarkfile_height);
            }
            imagejpeg($dstim, $idir_base . $filename, 100);
            imagedestroy($dstim);
            $srcim = imagecreatefromjpeg($tempFile);
            $dstim = imagecreatetruecolor($thnw, $thnh);
            $newim = imagecopyresampled($dstim, $srcim, 0, 0, 0, 0, $thnw, $thnh, $width, $height);
            imagejpeg($dstim, $thdir_base . $filename);
            imagedestroy($dstim);

            $query = "insert into csct_stmpl_photos values ('', '" . $_REQUEST['tdata_id'] . "', '" . $_REQUEST['page_type'] .
                "', '" . $_REQUEST['div_id'] . "', '" . $filename . "')";
            $this->dbh->exec($query);

            return str_replace($_SERVER['DOCUMENT_ROOT'], '', $filename);
        }

    }

    function stmpl_gallery_photo_process()
    {
        if (isset($_POST['del_photo']) && $_POST['del_photo']) {
            $id = 0;
            $query = "select * from csct_stmpl_photos where id=:id";
            $ssql = $this->dbh->prepare($query);
            $ssql->bindParam(':id', $id, PDO::PARAM_INT);
            $query = "delete from csct_stmpl_photos where id=:id";
            $sql = $this->dbh->prepare($query);
            $sql->bindParam(':id', $id, PDO::PARAM_INT);
            foreach ($_POST['del_photo'] as $id) {
                $ssql->execute();
                $file_data = $ssql->fetch();
                $ssql->closeCursor();
                unlink($_SERVER['DOCUMENT_ROOT'] . "/uploads/upimg/phg_images/" . $file_data['ph_file']);
                unlink($_SERVER['DOCUMENT_ROOT'] . "/uploads/upimg/phg_thumbnails/" . $file_data['ph_file']);
                $sql->execute();
                $sql->closeCursor();
            }
        }
    }

    function upd_lib_dtmpl()
    {
        $query = "update csct_library set dtmpl_id=" . $_POST['value'] . " where id=" . $_POST['pk'];
        $this->dbh->exec($query);
    }

    function upd_lib_name()
    {
        $query = "update csct_library set name=:lib_name where id=" . $_POST['pk'];
        $sql = $this->dbh->prepare($query);
        $sql->bindParam(':lib_name', $_POST['value'], PDO::PARAM_STR);
        $sql->execute();
        $sql->closeCursor();
    }

    function add_library()
    {
        $query = "insert into csct_library (user_id, name, dtmpl_id) values ('" . $this->registry->user_id .
            "', :name, :dtmpl_id)";
        $sql = $this->dbh->prepare($query);
        $sql->bindParam(':name', $_POST['lib_name'], PDO::PARAM_STR);
        $sql->bindParam(':dtmpl_id', $_POST['dtmpl_id'], PDO::PARAM_INT);
        $sql->execute();
        $sql->closeCursor();
        return $this->dbh->lastInsertId();
    }

    function del_library($lib_id)
    {
        $query = "delete from csct_pic_link where flib_id in (select id from csct_tdata_flib where lib_id=" .
            $lib_id . ")";
        $this->dbh->exec($query);
        $query = "delete from csct_tdata_flib where lib_id=" . $lib_id;
        $this->dbh->exec($query);
        $query = "delete from csct_lib_content_names where data_id in (select id from csct_lib_content where ref_id=" .
            $lib_id;
        $this->dbh->exec($query);
        $query = "delete from csct_lib_content where ref_id=" . $lib_id;
        $this->dbh->exec($query);
        $query = "delete from csct_library where id=" . $lib_id;
        $this->dbh->exec($query);

    }

    function laddrqty($tdata_id)
    {
        $query = "select count(id) from csct_lib_content where address=(select address from csct_lib_content where id=" .
            $tdata_id . ")";
        $addrqty = current($this->dbh->queryFetchRow($query));
        return ($addrqty);
    }

    function get_lc_data($id)
    {
        $lid = 0;
        $query = "select lc.*, lib.dtmpl_id, lib.site_users from csct_lib_content lc, csct_library lib where lc.ref_id=lib.id and lc.id=" .
            $id;
        $lc_data = $this->dbh->queryFetchRow($query);
        $query = "select * from csct_lib_content_names where data_id=:data_id and lang_id=:lid";
        $sql = $this->dbh->prepare($query);
        $sql->bindParam(':data_id', $id, PDO::PARAM_INT);
        $sql->bindParam(':lid', $lid, PDO::PARAM_INT);
        if (app()->ml) {
            foreach (app()->csct_langs as $lid => $lang_name) {
                $sql->execute();
                $result = $sql->fetch();
                $sql->closeCursor();
                $lc_data['name'][$lid] = $result['name'];
            }
        }
        else {
            $sql->execute();
            $result = $sql->fetch();
            $sql->closeCursor();
            $lc_data['name'] = $result['name'];
        }
        return $lc_data;
    }

    function get_site_user($lc_id)
    {
        $query = "select *, DATE_FORMAT(regdate,'%d.%m.%Y') fregdate from site_users where lc_id=" . $lc_id;
        $result = $this->dbh->queryFetchRow($query);
        return $result ? $result:null;
    }

    function lc_process()
    {

        $query = "select * from csct_library where id=" . $_POST['lib_id'];
        $libResult = $this->dbh->queryFetchRow($query);
        if ($libResult['site_users'])
            $this->dbh->query("update site_users set auth_data='' where lc_id=" . $_POST['tdata_id']);
        if (!$_POST['address']) {
            $hdr = app()->ml ? $_POST['name_' . app()->lang_main]:$_POST['name'];
            $address = strtolower($this->trlit($hdr));
        }
        else
            $address = strtolower($this->trlit($_POST['address']));

        $query = "update csct_lib_content set address=:address where id=" . $_POST['tdata_id'];
        $lusql = $this->dbh->prepare($query);
        $lusql->bindParam(':address', $address, PDO::PARAM_STR);
        $lusql->execute();
        $lusql->closeCursor();

        $lid = 0;
        $query = "select * from csct_lib_content_names where data_id=:data_id and lang_id=:lid";
        $sql = $this->dbh->prepare($query);
        $sql->bindParam(':data_id', $_POST['tdata_id'], PDO::PARAM_INT);
        $sql->bindParam(':lid', $lid, PDO::PARAM_INT);

        $query = "insert into csct_lib_content_names (data_id, lang_id, name) values ('" . $_POST['tdata_id'] .
            "', :lid, :name)";
        $isql = $this->dbh->prepare($query);
        $isql->bindParam(':lid', $lid, PDO::PARAM_INT);
        $isql->bindParam(':name', $name, PDO::PARAM_STR);

        $query = "update csct_lib_content_names set name=:name where lang_id=:lid and data_id=" . $_POST['tdata_id'];
        $usql = $this->dbh->prepare($query);
        $usql->bindParam(':lid', $lid, PDO::PARAM_INT);
        $usql->bindParam(':name', $name, PDO::PARAM_STR);

        if (app()->ml) {
            foreach (app()->csct_langs as $lid => $lang_name) {
                $name = $_POST['name_' . $lid];
                $sql->execute();
                $result = $sql->fetch();
                $sql->closeCursor();
                if ($result) {
                    $usql->execute();
                    $usql->closeCursor();
                }
                else {
                    $isql->execute();
                    $isql->closeCursor();
                }
            }
        }
        else {
            $name = $_POST['name'];
            $sql->execute();
            $result = $sql->fetch();
            $sql->closeCursor();
            if ($result) {
                $usql->execute();
                $usql->closeCursor();
            }
            else {
                $isql->execute();
                $isql->closeCursor();
            }
        }
        if (isset($_POST['userData'])) {
            $query = "update site_users set email=:email, fio=:fio, tel=:tel, status=:status where lc_id=:lc_id";
            $sql = $this->dbh->prepare($query);
            $sql->bindParam(':email', $_POST['uEmail'], PDO::PARAM_STR);
            $sql->bindParam(':fio', $_POST['uFio'], PDO::PARAM_STR);
            $sql->bindParam(':tel', $_POST['uPhone'], PDO::PARAM_STR);
            $sql->bindParam(':status', $_POST['uActive'], PDO::PARAM_INT);
            $sql->bindParam(':lc_id', $_POST['tdata_id'], PDO::PARAM_INT);
            $sql->execute();
            $sql->closeCursor();
        }
    }

    function create_lc($name = '', $lib_id = 0)
    {
        $lid = app()->ml ? app()->lang_main:0;
        //$name = $_POST['name'] ? $_POST['name']:'Новый элемент';
        $query = "select max(num) from csct_lib_content where ref_id=" . $lib_id;
        $result = current($this->dbh->queryFetchRow($query));
        $num = $result ? $result + 1:1;
        $address = strtolower($this->trlit($name));
        $query = "insert into csct_lib_content (user_id, num, address, status, ref_id) values ('" . $this->
            registry->user_id . "', '" . $num . "', '" . $address . "', '1', '" . $lib_id . "')";
        $this->dbh->exec($query);
        $tdata_id = $this->dbh->lastInsertId();
        $query = "insert into csct_lib_content_names (data_id, lang_id, name) values ('" . $tdata_id .
            "', :lid, :name)";
        $isql = $this->dbh->prepare($query);
        $isql->bindParam(':lid', $lid, PDO::PARAM_INT);
        $isql->bindParam(':name', $name, PDO::PARAM_STR);
        $isql->execute();
        $isql->closeCursor();
        return $tdata_id;
    }

    function get_trigger_data($id)
    {
        $query = "select * from csct_triggers where id=" . $id;
        return $this->dbh->queryFetchRow($query);
    }

    function del_trigger($id)
    {
        $query = "delete from csct_triggers where id=" . $id;
        $this->dbh->exec($query);
    }

    function add_trigger()
    {
        $query = "insert into csct_triggers (user_id, trgr_name, trgr_cname, content, status) values ('" . $this->
            registry['user_id'] . "', :trgr_name, :trgr_cname, '', '1')";
        $sql = $this->dbh->prepare($query);
        $sql->bindParam(':trgr_name', $_POST['header'], PDO::PARAM_STR);
        $sql->bindParam(':trgr_cname', $_POST['trgr_cname'], PDO::PARAM_STR);
        $sql->execute();
        $sql->closeCursor();
        $trgr_id = $this->dbh->lastInsertId();
        return $trgr_id;
    }

    function process_trigger()
    {
        $query = "update csct_triggers set trgr_name=:trgr_name, trgr_cname=:trgr_cname, content=:content where id=" .
            $_POST['trgr_id'];
        $sql = $this->dbh->prepare($query);
        $content = trim($_POST['trgr_cnt']);
        $content = preg_replace('/^<\?php/iU', '', $content);
        $content = preg_replace('/^<\?/iU', '', $content);
        $content = preg_replace('/\?>$/iU', '', $content);
        $content = trim($content);
        $sql->bindParam(':trgr_name', $_POST['trgr_name'], PDO::PARAM_STR);
        $sql->bindParam(':trgr_cname', $_POST['trgr_cname'], PDO::PARAM_STR);
        $sql->bindParam(':content', $content, PDO::PARAM_STR);

        $sql->execute();
        $sql->closeCursor();
    }

    function ch_luser($lib_id, $is_user)
    {
        $query = "update csct_library set site_users=0";
        $this->dbh->exec($query);
        if (!$is_user) {
            $query = "update csct_library set site_users=1 where id=" . $lib_id;
            $this->dbh->exec($query);
        }
    }

    function ch_lmgroup($lib_id, $is_mgroup)
    {
        $query = "update csct_library set mail_groups=0";
        $this->dbh->exec($query);
        if (!$is_mgroup) {
            $query = "update csct_library set mail_groups=1 where id=" . $lib_id;
            $this->dbh->exec($query);
        }
    }

    function get_all_constants()
    {
        $query = "select * from csct_mconstants";
        $result = $this->dbh->queryFetchAll($query);
        $query = "select * from csct_mconstants_text where data_id=:id";
        $id = 0;
        $sql = $this->dbh->prepare($query);
        $sql->bindParam(':id', $id, PDO::PARAM_INT);
        foreach ($result as $key => $item) {
            $text_data = array();
            $id = $item['id'];
            $sql->execute();
            $tdata = $sql->fetchAll();
            $sql->closeCursor();
            foreach ($tdata as $titem)
                $text_data[$titem['lang_id']] = $titem;
            $result[$key]['text'] = $text_data;
        }
        return $result;
    }

    function bf_process()
    {
        $id = 0;
        $header = '';
        $mark = '';
        $address = '';
        $file_link = '';
        $alttext = '';
        $img_style = '';
        $a_style = '';
        $active = 1;
        $ltype = 0;
        $lpage = 0;
        $query = "insert into csct_mconstants_text values ('', :id, :lid, :ctext)";
        $ins_sql = $this->dbh->prepare($query);
        $ins_sql->bindParam(':lid', $lid, PDO::PARAM_INT);
        $ins_sql->bindParam(':ctext', $alttext, PDO::PARAM_STR);
        $ins_sql->bindParam(':id', $id, PDO::PARAM_STR);
        if (isset($_POST['constant_id']) && $_POST['constant_id']) {
            $query = "select count(id) from csct_mconstants where mark=:mark and id<>:id";
            $isql = $this->dbh->prepare($query);
            $isql->bindParam(':id', $id, PDO::PARAM_INT);
            $isql->bindParam(':mark', $mark, PDO::PARAM_STR);
            $query = "update csct_mconstants set header=:header, mark=:mark, file_link=:file_link, active=:active where id=:id";
            $usql = $this->dbh->prepare($query);
            $usql->bindParam(':id', $id, PDO::PARAM_INT);
            $usql->bindParam(':mark', $mark, PDO::PARAM_STR);
            $usql->bindParam(':file_link', $file_link, PDO::PARAM_STR);
            $usql->bindParam(':header', $header, PDO::PARAM_STR);
            $usql->bindParam(':active', $active, PDO::PARAM_INT);

            $query = "update csct_mconstants_text set ctext=:ctext where data_id=:id and lang_id=:lid";
            $upd_sql = $this->dbh->prepare($query);
            $upd_sql->bindParam(':lid', $lid, PDO::PARAM_INT);
            $upd_sql->bindParam(':id', $id, PDO::PARAM_INT);
            $upd_sql->bindParam(':ctext', $alttext, PDO::PARAM_STR);

            $query = "select * from csct_mconstants_text where data_id=:id and lang_id=:lid";
            $sel_sql = $this->dbh->prepare($query);
            $sel_sql->bindParam(':lid', $lid, PDO::PARAM_INT);
            $sel_sql->bindParam(':id', $id, PDO::PARAM_INT);

            foreach ($_POST['constant_id'] as $key => $id) {
                $ltype = 0;
                $lpage = 0;
                $mark = $_POST['mark'][$key];
                $isql->execute();
                $ib = current($isql->fetch());
                $isql->closeCursor();
                if ($ib) {
                    $n = 1;
                    do {
                        $omark = $mark;
                        $mark .= $n;
                        $isql->execute();
                        $ib = current($isql->fetch());
                        $isql->closeCursor();
                        if (!$ib)
                            break;
                        $mark = $omark;
                        $n++;
                    } while (1);
                }
                $file_link = $_POST['file_link'][$key];
                $header = $_POST['header'][$key];
                $active = isset($_POST['active'][$key]) ? 1:0;
                $usql->execute();
                $usql->closeCursor();

                if (app()->ml) {
                    foreach (app()->csct_langs as $lid => $lang_name) {
                        $sel_sql->execute();
                        $text_data = $sel_sql->fetch();
                        $sel_sql->closeCursor();
                        $alttext = $_POST['ctext_' . $lid][$key];

                        if ($text_data) {
                            $upd_sql->execute();
                            $upd_sql->closeCursor();
                        }
                        else {
                            $ins_sql->execute();
                            $ins_sql->closeCursor();
                        }
                    }
                }
                else {
                    $lid = 0;
                    $sel_sql->execute();
                    $text_data = $sel_sql->fetch();
                    $sel_sql->closeCursor();
                    $alttext = $_POST['ctext'][$key];
                    if ($text_data) {
                        $upd_sql->execute();
                        $upd_sql->closeCursor();
                    }
                    else {
                        $ins_sql->execute();
                        $ins_sql->closeCursor();
                    }
                }
            }
        }
        if ($_POST['nheader']) {
            $query = "select count(id) from csct_mconstants where mark=:mark";
            $isql = $this->dbh->prepare($query);
            $isql->bindParam(':mark', $mark, PDO::PARAM_STR);
            $query = "insert into csct_mconstants (header, mark, file_link, active) values (:header, :mark, :file_link, :active)";
            $usql = $this->dbh->prepare($query);
            $active = isset($_POST['nactive']) ? 1:0;

            $usql->bindParam(':mark', $_POST['nmark'], PDO::PARAM_STR);
            $usql->bindParam(':file_link', $_POST['nfile_link'], PDO::PARAM_STR);
            $usql->bindParam(':header', $_POST['nheader'], PDO::PARAM_STR);
            $usql->bindParam(':active', $active, PDO::PARAM_INT);

            $usql->execute();
            $usql->closeCursor();
            $id = $this->dbh->lastInsertId();
            if (app()->ml) {
                foreach (app()->csct_langs as $lid => $lang_name) {
                    $alttext = $_POST['ntext_' . $lid];
                    $ins_sql->execute();
                    $ins_sql->closeCursor();
                }
            }
            else {
                $lid = 0;
                $alttext = $_POST['nalttext'];
                $ins_sql->execute();
                $ins_sql->closeCursor();

            }
        }
    }

    function resetPass()
    {
        if (!$_POST['pw'])
            $pw = substr(md5(time()), 0, 5);
        else
            $pw = $_POST['pw'];
        $query = "select * from site_users where id=" . $_POST['uId'];
        $data = $this->dbh->queryFetchRow($query);
        $pwHash = md5(md5($pw) . substr(base64_encode($data['u_login']), 0, strlen($data['u_login'])));
        $query = "update site_users set pw='" . $pwHash . "' where id=" . $_POST['uId'];
        $this->dbh->exec($query);
        echo 'Пароль установлен: ' . $pw;
    }

}
?>