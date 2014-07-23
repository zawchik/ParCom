<?php
/**
 * model_cmain
 * 
 * @package ParCom
 * @author Andrei Zawialov
 * @copyright 2013
 * @version $Id$
 * @access public
 */
class model_cmain extends model_base
{

    private $pcopied = array();
    private $pgcopied = array();

    /**
     * model_cmain::site_pages()
     * Получает страницы сайта
     * 
     * @param mixed $domain_id идентификатор домена (необязательное поле)
     * @param bool $show_children показывать дочерние страницы
     * @param string $qstr строка поиска
     * @param bool $check_status проверка статуса
     * @param bool $check_lang
     * @param bool $incall
     * @return
     */
    function site_pages($site_id = null, $show_children = false, $qstr = '', $check_status = false, $check_lang = false,
        $incall = false)
    {
        $query = "select mt.*, nt.name, pl.id cid, pgl.id pid from 
csct_page_groups mt
left join csct_page_groups_names nt on mt.id=nt.data_id
left join csct_pgr_link pl on mt.id=pl.pg_id
left join csct_pg_link pgl on mt.id=pgl.pg_id";
        if ($qstr)
            $query .= " where (mt.id='" . $qstr . "' or nt.header like '%" . $qstr . "%' or mt.address like '%" .
                $qstr . "%')";
        else
            $query .= " where mt.id not in (select data_id from csct_pgr_link) and mt.parent_page=0";
        if ($site_id && app()->md)
            $query .= " and (mt.use_md=0 or (mt.use_md=1 and mt.id in (select data_id from csct_site_links where data_type=1 and site_id=" .
                $site_id . ")))";
        if ($this->registry['user_settings']['acl'] == 1 && $this->registry->user_settings['permissions']['rlevel'] ==
            1 && $this->registry->user_settings['permissions']['sites'] && $incall && app()->md)
            $query .= " and (mt.use_md=1 and mt.id in (select data_id from csct_site_links where data_type=1 and site_id in (select site_id from csct_user_sites where user_id=" .
                $this->registry->user_id . ")))";
        if ($check_status !== false)
            $query .= " and mt.status=" . $check_status;
        if ($check_lang)
            $query .= " and (nt.lang_id=" . app()->lang_id . " or nt.lang_id=0)";
        if ($this->registry['user_settings']['acl'] == 1 && $this->registry->user_settings['permissions']['rlevel'] ==
            1 && $this->registry->user_settings['permissions']['only_permitted'] && $incall)
            $query .= " and mt.id in (select data_id from csct_userlinks where data_type=1 and user_id=" . $this->
                registry->user_id . ")";
        $query .= " group by mt.id order by mt.num asc";
        $pg_data = $this->dbh->queryFetchAll($query);
        if ($pg_data && $show_children) {
            foreach ($pg_data as $key => $pgrp) {
                $pg_data[$key]['sgroups'] = $this->get_subgroups($pgrp['id'], $site_id, $show_children, $check_status,
                    $check_lang, $incall);
                $pg_data[$key]['pages'] = $this->get_group_pages($pgrp['id'], $site_id, $show_children, $check_status,
                    $check_lang, $incall);
            }
        }

        $query = "select mt.id, mt.dtmpl_id, mt.address, nt.header, nt.subheader, nt.menu_name, mt.db_type, mt.status, pl.id cid, plg.id pid from csct_pages mt
left join csct_pages_text nt on mt.id=nt.data_id
left join csct_pages pl on pl.parent=mt.id
left join csct_page_groups plg on plg.parent_page=mt.id";
        if ($qstr)
            $query .= " where (mt.id='" . $qstr . "' or nt.header like '%" . $qstr . "%' or mt.address like '%" .
                $qstr . "%')";
        else
            $query .= " where mt.id not in (select data_id from csct_pg_link) and mt.parent=0";
        if ($site_id && app()->md)
            $query .= " and (mt.use_md=0 or (mt.use_md=1 and mt.id in (select data_id from csct_site_links where data_type=0 and site_id=" .
                $site_id . ")))";
        if ($this->registry['user_settings']['acl'] == 1 && $this->registry->user_settings['permissions']['rlevel'] ==
            1 && $this->registry->user_settings['permissions']['sites'] && $incall && app()->md)
            $query .= " and (mt.use_md=1 and mt.id in (select data_id from csct_site_links where data_type=0 and site_id in (select site_id from csct_user_sites where user_id=" .
                $this->registry->user_id . ")))";
        if ($check_status !== false)
            $query .= " and mt.status=" . $check_status;
        if ($check_lang)
            $query .= " and (nt.lang_id=" . app()->lang_id . " or nt.lang_id=0)";
        if ($this->registry['user_settings']['acl'] == 1 && $this->registry->user_settings['permissions']['rlevel'] ==
            1 && $this->registry->user_settings['permissions']['only_permitted'] && $incall)
            $query .= " and mt.id in (select data_id from csct_userlinks where data_type=0 and user_id=" . $this->
                registry->user_id . ")";
        $query .= " group by mt.id order by mt.num asc";
        $pages = $this->dbh->queryFetchAll($query);
        if ($pages && $show_children)
            foreach ($pages as $key => $page) {
                $pages[$key]['sgroups'] = $this->get_pagesubgroups($page['id'], $site_id, $show_children, $check_status,
                    $check_lang, $incall);
                $pages[$key]['pages'] = $this->get_subpages($page['id'], $site_id, $show_children, $check_status, $check_lang,
                    $incall);
            }

        return (array('pg_data' => $pg_data, 'pages' => $pages));
    }

    /**
     * model_cmain::get_group_pages()
     * 
     * @param mixed $pg_id
     * @param mixed $domain_id
     * @param bool $show_children
     * @param bool $check_status
     * @param bool $check_lang
     * @param bool $incall
     * @return
     */
    function get_group_pages($pg_id, $site_id = null, $show_children = false, $check_status = false, $check_lang = false,
        $incall = false, $start = 0, $limit = 0)
    {
        $query = "select mt.*, nt.header, nt.subheader, nt.menu_name, mt.db_type, mt.status, pl.id cid, plg.id pid from csct_pages mt left join csct_pages_text nt on mt.id=nt.data_id left join csct_pg_link pgl on mt.id=pgl.data_id left join csct_pages pl on mt.id=pl.parent left join csct_page_groups plg on plg.parent_page=mt.id where pgl.pg_id=:pg_id";
        if ($site_id && app()->md)
            $query .= " and (mt.use_md=0 or (mt.use_md=1 and mt.id in (select data_id from csct_site_links where data_type=0 and site_id=" .
                $site_id . ")))";
        if ($check_status !== false)
            $query .= " and mt.status=" . $check_status;
        if ($this->registry['user_settings']['acl'] == 1 && $this->registry->user_settings['permissions']['rlevel'] ==
            1 && $this->registry->user_settings['permissions']['sites'] && $incall && app()->md)
            $query .= " and (mt.use_md=1 and mt.id in (select data_id from csct_site_links where data_type=0 and site_id in (select site_id from csct_user_sites where user_id=" .
                $this->registry->user_id . ")))";
        if ($check_lang)
            $query .= " and (nt.lang_id=" . app()->lang_id . " or nt.lang_id=0)";
        if ($this->registry['user_settings']['acl'] == 1 && $this->registry->user_settings['permissions']['rlevel'] ==
            1 && $this->registry->user_settings['permissions']['only_permitted'] && $incall)
            $query .= " and mt.id in (select data_id from csct_userlinks where data_type=0 and user_id=" . $this->
                registry->user_id . ")";
        $query .= " group by mt.id order by pgl.num asc";
        if ($limit)
            $query .= " limit " . $start . ", " . $limit;
        $pg_sql = $this->dbh->prepare($query);
        $pg_sql->bindParam(':pg_id', $pg_id, PDO::PARAM_INT);
        $pg_sql->execute();
        $pg_pages = $pg_sql->fetchAll();
        $pg_sql->closeCursor();
        if ($pg_pages && $show_children)
            foreach ($pg_pages as $key => $page) {
                $pg_pages[$key]['sgroups'] = $this->get_pagesubgroups($page['id'], $site_id, $show_children, $check_status,
                    $incall);
                $pg_pages[$key]['pages'] = $this->get_subpages($page['id'], $site_id, $show_children, $check_status,
                    $incall);
            }
        if ($limit == 0)
            return $pg_pages;
        else {
            $query = "select count(mt.id) from csct_pages mt left join csct_pages_text nt on mt.id=nt.data_id left join csct_pg_link pgl on mt.id=pgl.data_id where pgl.pg_id=:pg_id";
            if ($site_id && app()->md)
                $query .= " and (mt.use_md=0 or (mt.use_md=1 and mt.id in (select data_id from csct_site_links where data_type=0 and site_id=" .
                    $site_id . ")))";
            if ($check_status !== false)
                $query .= " and mt.status=" . $check_status;
            if ($this->registry['user_settings']['acl'] == 1 && $this->registry->user_settings['permissions']['rlevel'] ==
                1 && $this->registry->user_settings['permissions']['sites'] && $incall && app()->md)
                $query .= " and (mt.use_md=1 and mt.id in (select data_id from csct_site_links where data_type=0 and site_id in (select site_id from csct_user_sites where user_id=" .
                    $this->registry->user_id . ")))";
            if ($check_lang)
                $query .= " and (nt.lang_id=" . app()->lang_id . " or nt.lang_id=0)";
            if ($this->registry['user_settings']['acl'] == 1 && $this->registry->user_settings['permissions']['rlevel'] ==
                1 && $this->registry->user_settings['permissions']['only_permitted'] && $incall)
                $query .= " and mt.id in (select data_id from csct_userlinks where data_type=0 and user_id=" . $this->
                    registry->user_id . ")";
            $pg_sql = $this->dbh->prepare($query);
            $pg_sql->bindParam(':pg_id', $pg_id, PDO::PARAM_INT);
            $pg_sql->execute();
            $result = $pg_sql->fetch();
            $pg_sql->closeCursor();
            return array('data' => $pg_pages, 'qty' => current($result));
        }

    }

    /**
     * model_cmain::get_subgroups()
     * 
     * @param mixed $pg_id
     * @param mixed $domain_id
     * @param bool $show_children
     * @param bool $check_status
     * @param bool $check_lang
     * @param bool $incall
     * @return
     */
    function get_subgroups($pg_id, $site_id = null, $show_children = false, $check_status = false, $check_lang = false,
        $incall = false)
    {
        $query = "select mt.*, nt.name, pl.id cid, pgl.id pid from 
csct_page_groups mt
left join csct_page_groups_names nt on mt.id=nt.data_id
left join csct_pgr_link pl on mt.id=pl.pg_id
left join csct_pg_link pgl on mt.id=pgl.pg_id
where mt.id in (select data_id from csct_pgr_link where pg_id=:pg_id)";
        if ($site_id && app()->md)
            $query .= " and (mt.use_md=0 or (mt.use_md=1 and mt.id in (select data_id from csct_site_links where data_type=1 and site_id=" .
                $site_id . ")))";
        if ($check_status !== false)
            $query .= " and mt.status=" . $check_status;
        if ($this->registry['user_settings']['acl'] == 1 && $this->registry->user_settings['permissions']['rlevel'] ==
            1 && $this->registry->user_settings['permissions']['sites'] && $incall && app()->md)
            $query .= " and (mt.use_md=1 and mt.id in (select data_id from csct_site_links where data_type=1 and site_id in (select site_id from csct_user_sites where user_id=" .
                $this->registry->user_id . ")))";
        if ($check_lang)
            $query .= " and (nt.lang_id=" . app()->lang_id . " or nt.lang_id=0)";
        if ($this->registry['user_settings']['acl'] == 1 && $this->registry->user_settings['permissions']['rlevel'] ==
            1 && $this->registry->user_settings['permissions']['only_permitted'] && $incall)
            $query .= " and mt.id in (select data_id from csct_userlinks where data_type=1 and user_id=" . $this->
                registry->user_id . ")";
        $query .= " group by mt.id order by pl.num asc";
        $pgr_sql = $this->dbh->prepare($query);
        $pgr_sql->bindParam(':pg_id', $pg_id, PDO::PARAM_INT);
        $pgr_sql->execute();
        $pg_groups = $pgr_sql->fetchAll();
        $pgr_sql->closeCursor();
        if ($show_children) {
            foreach ($pg_groups as $key => $group) {
                $pg_groups[$key]['sgroups'] = $this->get_subgroups($group['id'], $site_id, $show_children, $check_status,
                    $check_lang, $incall);
                $pg_groups[$key]['pages'] = $this->get_group_pages($group['id'], $site_id, $show_children, $check_status,
                    $check_lang, $incall);
            }
        }
        return $pg_groups;
    }

    /**
     * model_cmain::get_pagesubgroups()
     * 
     * @param mixed $pg_id
     * @param mixed $domain_id
     * @param bool $show_children
     * @param bool $check_status
     * @param bool $check_lang
     * @param bool $incall
     * @return
     */
    function get_pagesubgroups($pg_id, $site_id = null, $show_children = false, $check_status = false, $check_lang = false,
        $incall = false)
    {
        $query = "select mt.*, nt.name, pl.id cid, pgl.id pid from 
csct_page_groups mt
left join csct_page_groups_names nt on mt.id=nt.data_id
left join csct_pgr_link pl on mt.id=pl.pg_id
left join csct_pg_link pgl on mt.id=pgl.pg_id
where mt.parent_page=:pg_id";
        if ($site_id && app()->md)
            $query .= " and (mt.use_md=0 or (mt.use_md=1 and mt.id in (select data_id from csct_site_links where data_type=1 and site_id=" .
                $site_id . ")))";
        if ($check_status !== false)
            $query .= " and mt.status=" . $check_status;
        if ($this->registry['user_settings']['acl'] == 1 && $this->registry->user_settings['permissions']['rlevel'] ==
            1 && $this->registry->user_settings['permissions']['sites'] && $incall && app()->md)
            $query .= " and (mt.use_md=1 and mt.id in (select data_id from csct_site_links where data_type=1 and site_id in (select site_id from csct_user_sites where user_id=" .
                $this->registry->user_id . ")))";
        if ($check_lang)
            $query .= " and (nt.lang_id=" . app()->lang_id . " or nt.lang_id=0)";
        if ($this->registry['user_settings']['acl'] == 1 && $this->registry->user_settings['permissions']['rlevel'] ==
            1 && $this->registry->user_settings['permissions']['only_permitted'] && $incall)
            $query .= " and mt.id in (select data_id from csct_userlinks where data_type=1 and user_id=" . $this->
                registry->user_id . ")";
        $query .= " group by mt.id order by mt.num asc";
        $pgr_sql = $this->dbh->prepare($query);
        $pgr_sql->bindParam(':pg_id', $pg_id, PDO::PARAM_INT);
        $pgr_sql->execute();
        $pg_groups = $pgr_sql->fetchAll();
        $pgr_sql->closeCursor();
        if ($show_children) {
            foreach ($pg_groups as $key => $group) {
                $pg_groups[$key]['sgroups'] = $this->get_subgroups($group['id'], $site_id, $show_children, $check_status,
                    $check_lang, $incall);
                $pg_groups[$key]['pages'] = $this->get_group_pages($group['id'], $site_id, $show_children, $check_status,
                    $check_lang, $incall);
            }
        }
        return $pg_groups;
    }

    private function get_all_sgroups($pid)
    {
        /*
        $query = "select data_id from csct_pgr_link where pg_id=:pg_id";
        $sql = $this->dbh->prepare($query);
        $pg_id = 0;
        $sql->bindParam(':pg_id', $pg_id, PDO::PARAM_INT);
        */
        $ret = array();
        $query = "select id from csct_page_groups where parent_page=" . $pid;
        $result = $this->dbh->query($query)->fetchAll(PDO::FETCH_COLUMN);
        $ret = $result;
        if ($result) {
            do {
                $query = "select data_id from csct_pgr_link where pg_id in (" . join(",", $result) . ")";
                $result = $this->dbh->query($query)->fetchAll(PDO::FETCH_COLUMN);

                if (!$result)
                    break;
                $ret = array_merge($ret, $result);
            } while (1);
        }
        return $ret;
    }

    /**
     * model_cmain::get_subpages()
     * 
     * @param mixed $pg_id
     * @param mixed $domain_id
     * @param bool $show_children
     * @param bool $menu_only
     * @param bool $check_status
     * @param bool $check_lang
     * @param bool $incall
     * @return
     */
    function get_subpages($pg_id, $site_id = null, $show_children = false, $menu_only = false, $check_status = false,
        $check_lang = false, $incall = false, $start = 0, $limit = 0)
    {
        $query = "select mt.*, nt.header, nt.menu_name, pl.id cid, plg.id pid from csct_pages mt
left join csct_pages_text nt on mt.id=nt.data_id
left join csct_pages pl on pl.parent=mt.id
left join csct_page_groups plg on plg.parent_page=mt.id
where mt.parent=:pg_id";
        if ($menu_only)
            $query .= " and mt.show_menu=1";
        if ($site_id && app()->md)
            $query .= " and (mt.use_md=0 or (mt.use_md=1 and mt.id in (select data_id from csct_site_links where data_type=0 and site_id=" .
                $site_id . ")))";
        $pgl = $this->get_all_sgroups($pg_id);
        if ($incall && $pgl)
            $query .= " and mt.id not in (select data_id from csct_pg_link where pg_id in (" . join(",", $pgl) .
                "))";
        if ($check_status !== false)
            $query .= " and mt.status=" . $check_status;
        if ($this->registry['user_settings']['acl'] == 1 && $this->registry->user_settings['permissions']['rlevel'] ==
            1 && $this->registry->user_settings['permissions']['sites'] && $incall && app()->md)
            $query .= " and (mt.use_md=1 and mt.id in (select data_id from csct_site_links where data_type=0 and site_id in (select site_id from csct_user_sites where user_id=" .
                $this->registry->user_id . ")))";
        if ($check_lang)
            $query .= " and (nt.lang_id=" . app()->lang_id . " or nt.lang_id=0)";
        if ($this->registry['user_settings']['acl'] == 1 && $this->registry->user_settings['permissions']['rlevel'] ==
            1 && $this->registry->user_settings['permissions']['only_permitted'] && $incall)
            $query .= " and mt.id in (select data_id from csct_userlinks where data_type=0 and user_id=" . $this->
                registry->user_id . ")";
        $query .= " group by mt.id order by mt.num asc";
        if ($limit)
            $query .= " limit " . $start . ", " . $limit;
        $sql = $this->dbh->prepare($query);
        $sql->bindParam(':pg_id', $pg_id, PDO::PARAM_INT);
        $sql->execute();
        $data = $sql->fetchAll();
        $sql->closeCursor();
        if ($show_children)
            foreach ($data as $key => $page) {
                $data[$key]['sgroups'] = $this->get_pagesubgroups($page['id'], $site_id, $show_children, $check_status,
                    $check_lang, $incall);
                $data[$key]['pages'] = $this->get_subpages($page['id'], $site_id, $show_children, $menu_only, $check_status,
                    $check_lang, $incall);
            }
        if ($limit == 0)
            return $data;
        else {
            $query = "select count(mt.id) from csct_pages mt
left join csct_pages_text nt on mt.id=nt.data_id
where mt.parent=:pg_id";
            if ($menu_only)
                $query .= " and mt.show_menu=1";
            if ($site_id && app()->md)
                $query .= " and (mt.use_md=0 or (mt.use_md=1 and mt.id in (select data_id from csct_site_links where data_type=0 and site_id=" .
                    $site_id . ")))";
            if ($incall && $pgl)
                $query .= " and mt.id not in (select data_id from csct_pg_link where pg_id in (" . join(",", $pgl) .
                    "))";
            if ($check_status !== false)
                $query .= " and mt.status=" . $check_status;
            if ($this->registry['user_settings']['acl'] == 1 && $this->registry->user_settings['permissions']['rlevel'] ==
                1 && $this->registry->user_settings['permissions']['sites'] && $incall && app()->md)
                $query .= " and (mt.use_md=1 and mt.id in (select data_id from csct_site_links where data_type=0 and site_id in (select site_id from csct_user_sites where user_id=" .
                    $this->registry->user_id . ")))";
            if ($check_lang)
                $query .= " and (nt.lang_id=" . app()->lang_id . " or nt.lang_id=0)";
            if ($this->registry['user_settings']['acl'] == 1 && $this->registry->user_settings['permissions']['rlevel'] ==
                1 && $this->registry->user_settings['permissions']['only_permitted'] && $incall)
                $query .= " and mt.id in (select data_id from csct_userlinks where data_type=0 and user_id=" . $this->
                    registry->user_id . ")";
            $sql = $this->dbh->prepare($query);
            $sql->bindParam(':pg_id', $pg_id, PDO::PARAM_INT);
            $sql->execute();
            $result = $sql->fetch();
            $sql->closeCursor();
            return array('data' => $data, 'qty' => current($result));
        }
    }

    function search_page($pstr)
    {
        $params = explode("&", $pstr);
        $query = "select id from csct_pages";
        $conditions = array();
        foreach ($params as $param) {
            list($key, $value) = explode("=", $param);
            if ($key == 'group')
                $conditions[] = "id in (select data_id from csct_pg_link where pg_id=" . $value . ")";
            if ($key == 'parent')
                $conditions[] = "parent=" . $value;
            if ($key == 'status')
                $conditions[] = "status=" . $value;
            if ($key == 'archive')
                $conditions[] = "archive=" . $value;
        }
        $this->search_dtmpl($conditions, $params, 1);
        $query .= " where " . join(" and ", $conditions);
        return $this->dbh->query($query)->fetchAll(PDO::FETCH_COLUMN);
    }

    function search_pagegroup($pstr)
    {
        $params = explode("&", $pstr);
        $query = "select id from csct_page_groups";
        $conditions = array();
        foreach ($params as $param) {
            list($key, $value) = explode("=", $param);
            if ($key == 'group')
                $conditions[] = "id in (select data_id from csct_pgr_link where pg_id=" . $value . ")";
            if ($key == 'parent')
                $conditions[] = "parent_page=" . $value;
            if ($key == 'status')
                $conditions[] = "status=" . $value;
        }
        $this->search_dtmpl($conditions, $params, 4);
        $query .= " where " . join(" and ", $conditions);
        return $this->dbh->query($query)->fetchAll(PDO::FETCH_COLUMN);
    }

    function search_list_item($pstr)
    {
        $params = explode("&", $pstr);
        $query = "select id from csct_list_items";
        $conditions = array();
        foreach ($params as $param) {
            list($key, $value) = explode("=", $param);
            if ($key == 'parent')
                $conditions[] = "parent_id=" . $value;
            if ($key == 'status')
                $conditions[] = "status=" . $value;
            if ($key == 'archive')
                $conditions[] = "archive=" . $value;
        }
        $this->search_dtmpl($conditions, $params, 3);
        $query .= " where " . join(" and ", $conditions);
        return $this->dbh->query($query)->fetchAll(PDO::FETCH_COLUMN);
    }

    function search_dtmpl(&$conditions, $params, $dt)
    {
        $cname = '';
        $query = "select * from csct_dtmpl_fields where cname=:cname and dtmpl_id in (select id from csct_data_templates where dtmpl_type=" .
            $dt . ")";
        $fsql = $this->dbh->prepare($query);
        $fsql->bindParam(':cname', $cname, PDO::PARAM_STR);
        foreach ($params as $param) {
            list($key, $value) = explode("=", $param);
            if (strpos($key, 'field') !== false) {
                list($foo, $cname) = explode(":", $key);
                $fsql->execute();
                $fdata = $fsql->fetch();
                $fsql->closeCursor();
                if ($fdata) {
                    if (in_array($fdata['ftype'], array(0, 1)))
                        $conditions[] = "id in (select data_id from csct_tdata_fields where fvalue like '%" . $value .
                            "%' and field_id=" . $fdata['id'] . ")";
                    elseif ($fdata['ftype'] == 3 || $fdata['ftype'] == 4)
                        $conditions[] = "id in (select data_id from csct_tdata_fields where fnvalue = '" . $value .
                            "' and field_id=" . $fdata['id'] . ")";
                    elseif ($fdata['ftype'] == 7)
                        $conditions[] = "id in (select data_id from csct_tdata_fields where fdvalue = '" . $value .
                            "' and field_id=" . $fdata['id'] . ")";
                    elseif ($fdata['ftype'] == 5) {
                        $vls = explode(";", $value);
                        $conditions[] = "id in (select data_id from csct_tdata_fields where fnvalue in (" . join(",", $vls) .
                            ") and field_id=" . $fdata['id'] . ")";
                    }
                    elseif ($fdata['ftype'] == 2 || $fdata['ftype'] == 6) {
                        $vls = explode(";", $value);
                        $conditions[] = "id in (select data_id from csct_tdata_flib where item_id in (" . join(",", $vls) .
                            ") and flib_id=" . $fdata['id'] . ")";
                    }
                    elseif ($fdata['ftype'] == 8) {
                        $vls = explode(";", $value);
                        $conditions[] = "id in (select data_id from csct_dp_links where item_id in (" . join(",", $vls) .
                            ") and field_id=" . $fdata['id'] . ")";
                    }
                }
            }
        }
    }

    /**
     * model_cmain::get_page_data()
     * 
     * @param mixed $tdata_id
     * @param bool $get_text
     * @param bool $check_domain
     * @param bool $check_lang
     * @return
     */
    function get_page_data($tdata_id, $get_text = true, $check_domain = false, $check_lang = false, $incall = false,
        $pp = false)
    {
        $query = "select cdb.*, ";
        if ($incall)
            $query .= "DATE_FORMAT(cdb.crtime, '%d.%m.%Y %H:%i') fcrtime, DATE_FORMAT(cdb.edtime, '%d.%m.%Y %H:%i') fedtime, u1.name cr_user_name, u2.name ed_user_name, ";

        $query .= "cdbt.header from csct_pages cdb, csct_pages_text cdbt";
        if ($incall)
            $query .= ", csct_users u1, csct_users u2";
        $query .= " where cdb.id=cdbt.data_id";
        if (is_array($tdata_id))
            $query .= " and cdb.id in (" . join(", ", $tdata_id) . ")";
        else
            $query .= " and cdb.id=" . $tdata_id;

        if ($incall)
            $query .= " and u1.id=cdb.user_id and u2.id=cdb.ed_user_id";

        if ($check_domain && app()->md && app()->site_id)
            $query .= " and (cdb.use_md=0 or (cdb.use_md=1 and cdb.id in (select data_id from csct_site_links where data_type=0 and site_id=" .
                app()->site_id . ")))";
        $query .= " group by cdb.id";
        if (is_array($tdata_id))
            $page_data = $this->dbh->queryFetchAll($query);
        else
            $page_data = $this->dbh->queryFetchRow($query);

        if ($pp && isset($_REQUEST['fields'])) {
            if (in_array('lib_id', $_REQUEST['fields'])) {
                $id = 0;
                $query = "select name from csct_library where id=:pid";
                $sql = $this->dbh->prepare($query);
                if (is_array($tdata_id)) {
                    foreach ($page_data as $key => $page) {
                        if ($page['lib_id']) {
                            $sql->execute(array(':pid' => $page['lib_id']));
                            $page_data[$key]['lib_name'] = current($sql->fetch());
                            $sql->closeCursor();
                        }
                    }
                }
                else {
                    if ($page_data['lib_id']) {
                        $sql->execute(array(':pid' => $page_data['lib_id']));
                        $page_data['lib_name'] = current($sql->fetch());
                        $sql->closeCursor();
                    }
                }
            }
            if (in_array('template', $_REQUEST['fields'])) {
                $id = 0;
                $query = "select tmpl_name from csct_templates where id=:pid";
                $sql = $this->dbh->prepare($query);
                if (is_array($tdata_id)) {
                    foreach ($page_data as $key => $page) {
                        if ($page['template']) {
                            $sql->execute(array(':pid' => $page['template']));
                            $page_data[$key]['tmpl_name'] = current($sql->fetch());
                            $sql->closeCursor();
                        }
                    }
                }
                else {
                    if ($page_data['template']) {
                        $sql->execute(array(':pid' => $page_data['template']));
                        $page_data['tmpl_name'] = current($sql->fetch());
                        $sql->closeCursor();
                    }
                }
            }
            if (in_array('subtemplate', $_REQUEST['fields'])) {
                $query = "select stmpl_name from csct_subtemplates where id=:pid";
                $sql = $this->dbh->prepare($query);
                if (is_array($tdata_id)) {
                    foreach ($page_data as $key => $page) {
                        if ($page['subtemplate']) {
                            $sql->execute(array(':pid' => $page['subtemplate']));
                            $page_data[$key]['stmpl_name'] = current($sql->fetch());
                            $sql->closeCursor();
                        }
                    }
                }
                else {
                    if ($page_data['subtemplate']) {
                        $sql->execute(array(':pid' => $page_data['subtemplate']));
                        $page_data['stmpl_name'] = current($sql->fetch());
                        $sql->closeCursor();
                    }
                }
            }
            if (in_array('dtmpl', $_REQUEST['fields'])) {
                $query = "select dtmpl_name from csct_data_templates where id=:pid";
                $sql = $this->dbh->prepare($query);
                if (is_array($tdata_id)) {
                    foreach ($page_data as $key => $page) {
                        if ($page['dtmpl_id']) {
                            $sql->execute(array(':pid' => $page['dtmpl_id']));
                            $page_data[$key]['dtmpl_name'] = current($sql->fetch());
                            $sql->closeCursor();
                        }
                        if ($page['dtmpl_id_lc']) {
                            $sql->execute(array(':pid' => $page['dtmpl_id_lc']));
                            $page_data[$key]['lc_dtmpl_name'] = current($sql->fetch());
                            $sql->closeCursor();
                        }
                    }
                }
                else {
                    if ($page_data['dtmpl_id']) {
                        $sql->execute(array(':pid' => $page_data['dtmpl_id']));
                        $page_data['dtmpl_name'] = current($sql->fetch());
                        $sql->closeCursor();
                    }
                    if ($page_data['dtmpl_id_lc']) {
                        $sql->execute(array(':pid' => $page_data['dtmpl_id_lc']));
                        $page_data['lc_dtmpl_name'] = current($sql->fetch());
                        $sql->closeCursor();
                    }
                }
            }
            $query = "select snp_name from csct_snippets where id=:pid";
            $sql = $this->dbh->prepare($query);
            if (is_array($tdata_id)) {
                foreach ($page_data as $key => $page) {
                    if ($page['page_snp'] && in_array('page_snp', $_REQUEST['fields'])) {
                        $sql->execute(array(':pid' => $page['page_snp']));
                        $page_data[$key]['page_snp_name'] = current($sql->fetch());
                        $sql->closeCursor();
                    }
                    if ($page['snp_list'] && in_array('snp_list', $_REQUEST['fields'])) {
                        $sql->execute(array(':pid' => $page['snp_list']));
                        $page_data[$key]['snp_list_name'] = current($sql->fetch());
                        $sql->closeCursor();
                    }
                    if ($page['snp_list_item'] && in_array('snp_list_item', $_REQUEST['fields'])) {
                        $sql->execute(array(':pid' => $page['snp_list_item']));
                        $page_data[$key]['snp_list_item_name'] = current($sql->fetch());
                        $sql->closeCursor();
                    }
                    /*
                    if ($page['photo_snp']) {
                    $sql->execute(array(':pid' => $page['snp_list_item']));
                    $page_data[$key]['photo_snp_name'] = current($sql->fetch());
                    $sql->closeCursor();
                    }
                    */
                }
            }
            else {
                if ($page_data['page_snp'] && in_array('page_snp', $_REQUEST['fields'])) {
                    $sql->execute(array(':pid' => $page_data['page_snp']));
                    $page_data['page_snp_name'] = current($sql->fetch());
                    $sql->closeCursor();
                }
                if ($page_data['snp_list'] && in_array('snp_list', $_REQUEST['fields'])) {
                    $sql->execute(array(':pid' => $page_data['snp_list']));
                    $page_data['snp_list_name'] = current($sql->fetch());
                    $sql->closeCursor();
                }
                if ($page_data['snp_list_item'] && in_array('snp_list_item', $_REQUEST['fields'])) {
                    $sql->execute(array(':pid' => $page_data['snp_list_item']));
                    $page_data['snp_list_item_name'] = current($sql->fetch());
                    $sql->closeCursor();
                }
                /*
                if ($page_data['photo_snp']) {
                $sql->execute(array(':pid' => $page_data['snp_list_item']));
                $page_data['photo_snp_name'] = current($sql->fetch());
                $sql->closeCursor();
                }
                */
            }

        }

        if (is_array($tdata_id)) {
            $query = "select header from csct_pages_text where data_id=:pid limit 1";
            $sql = $this->dbh->prepare($query);
            foreach ($page_data as $key => $page) {
                if ($page['parent']) {
                    $sql->execute(array(':pid' => $page['parent']));
                    $page_data[$key]['parent_name'] = current($sql->fetch());
                    $sql->closeCursor();
                }
            }
        }
        else {
            if ($page_data['parent'])
                $page_data['parent_name'] = current($this->dbh->queryFetchRow('select header from csct_pages_text where data_id=' .
                    $page_data['parent'] . ' limit 1'));
        }
        if ($incall) {
            $query = "select user_id from csct_userlinks where data_id=:id and data_type=0";
            $sql = $this->dbh->prepare($query);

            if (is_array($tdata_id)) {
                foreach ($page_data as $key => $page) {
                    $sql->execute(array(':id' => $page['id']));
                    $ouresult = $sql->fetchAll(PDO::FETCH_COLUMN);
                    $sql->closeCursor();
                    $page_data[$key]['ousers'] = $ouresult;
                }
            }
            else {
                $sql->execute(array(':id' => $tdata_id));
                $ouresult = $sql->fetchAll(PDO::FETCH_COLUMN);
                $sql->closeCursor();
                $page_data['ousers'] = $ouresult;
            }
        }
        if ($pp && isset($_REQUEST['fields']) && in_array('ousers', $_REQUEST['fields'])) {

            $query = "select u.name from csct_userlinks ul, csct_users u where ul.data_id=:id and ul.data_type=0 and u.id=ul.user_id";
            $sql = $this->dbh->prepare($query);

            if (is_array($tdata_id)) {
                foreach ($page_data as $key => $page) {
                    $sql->execute(array(':id' => $page['id']));
                    $ouresult = $sql->fetchAll(PDO::FETCH_COLUMN);
                    $sql->closeCursor();
                    $page_data[$key]['ousers_names'] = $ouresult;
                }
            }
            else {
                $sql->execute(array(':id' => $tdata_id));
                $ouresult = $sql->fetchAll(PDO::FETCH_COLUMN);
                $sql->closeCursor();
                $page_data['ousers_names'] = $ouresult;
            }

        }

        if (is_array($tdata_id)) {
            $query = "select header from csct_pages_text where data_id=:pid limit 1";
            $sql = $this->dbh->prepare($query);
            foreach ($page_data as $key => $page) {
                if ($page['plink']) {
                    $sql->execute(array(':pid' => $page['plink']));
                    $page_data[$key]['plink_name'] = current($sql->fetch());
                    $sql->closeCursor();
                }
            }
        }
        else {
            if ($page_data['plink'])
                $page_data['plink_name'] = current($this->dbh->queryFetchRow('select header from csct_pages_text where data_id=' .
                    $page_data['plink'] . ' limit 1'));
        }

        if (is_array($tdata_id)) {
            $query = "select * from csct_constants where data_id=:pid and data_type=1";
            $sql = $this->dbh->prepare($query);
            foreach ($page_data as $key => $page) {
                $sql->execute(array(':pid' => $page['id']));
                $page_data[$key]['constants'] = $sql->fetchAll();
                $sql->closeCursor();
            }
        }
        else {
            $query = "select * from csct_constants where data_id=" . $tdata_id . " and data_type=1";
            $page_data['constants'] = $this->dbh->queryFetchAll($query);
        }

        if ($pp && isset($_REQUEST['fields']) && in_array('groups', $_REQUEST['fields'])) {
            if (is_array($tdata_id)) {
                $query = "select pg.name from csct_page_groups_names pg, csct_pg_link pgl where pgl.data_id=:pid and pgl.pg_id=pg.data_id";
                $sql = $this->dbh->prepare($query);
                foreach ($page_data as $key => $page) {
                    $sql->execute(array(':pid' => $page['id']));
                    $page_data[$key]['groups'] = $sql->fetchAll(PDO::FETCH_COLUMN);
                    $sql->closeCursor();
                }
            }
            else {
                $query = "select pg.name from csct_page_groups_names pg, csct_pg_link pgl where pgl.data_id=" . $tdata_id .
                    " and pgl.pg_id=pg.data_id";
                $page_data['groups'] = $this->dbh->query($query)->fetchAll(PDO::FETCH_COLUMN);
            }
        }
        if (is_array($tdata_id)) {
            $query = "select pg_id from csct_pg_link where data_id=:pid";
            $sql = $this->dbh->prepare($query);
            foreach ($page_data as $key => $page) {
                $sql->execute(array(':pid' => $page['id']));
                $page_data[$key]['pg_link'] = $sql->fetchAll(PDO::FETCH_COLUMN);
                $sql->closeCursor();
            }
        }
        else {
            $query = "select pg_id from csct_pg_link where data_id=" . $tdata_id;
            $page_data['pg_link'] = $this->dbh->query($query)->fetchAll(PDO::FETCH_COLUMN);
        }

        if ($get_text) {
            if (is_array($tdata_id)) {

                if (!$check_lang) {
                    $query = "select * from csct_pages_text where data_id=:pid";
                    $sql = $this->dbh->prepare($query);
                    $text_data = array();
                    foreach ($page_data as $key => $page) {
                        $sql->execute(array(':pid' => $page['id']));
                        $result = $sql->fetchAll();
                        $sql->closeCursor();
                        foreach ($result as $item)
                            $text_data[$key][$item['lang_id']] = $item;
                    }
                }
                else {

                    $query = "select * from csct_pages_text where data_id=:pid and lang_id=:lid";
                    $sql = $this->dbh->prepare($query);
                    $text_data = array();
                    foreach ($page_data as $key => $page) {
                        $lang_id = (app()->ml && $page['use_ml']) ? app()->lang_id:0;
                        $sql->execute(array(':pid' => $page['id'], ':lid' => $lang_id));
                        $text_data[$key] = $sql->fetch();
                        $sql->closeCursor();
                    }

                }
            }
            else {
                $query = "select * from csct_pages_text where data_id=" . $tdata_id;
                if (!$check_lang) {
                    $text_data = array();
                    $result = $this->dbh->queryFetchAll($query);
                    foreach ($result as $item)
                        $text_data[$item['lang_id']] = $item;
                }
                else {
                    $lang_id = (app()->ml && $page_data['use_ml']) ? app()->lang_id:0;
                    $query .= " and lang_id=" . $lang_id;
                    $text_data = $this->dbh->queryFetchRow($query);
                }
            }
            return array('page_data' => $page_data, 'text_data' => $text_data);
        }
        else
            return $page_data;
    }

    function get_lpage_data($tdata_id, $get_text = true)
    {
        $query = "select cli.*, DATE_FORMAT(cli.crtime, '%d.%m.%Y %H:%i') fcrtime, DATE_FORMAT(cli.edtime, '%d.%m.%Y %H:%i') fedtime, u1.name cr_user_name, u2.name ed_user_name, clit.header, DATE_FORMAT(cli.dateofpub, '%d.%m.%Y') fdateofpub, p.dtmpl_id_lc dtmpl_id, p.use_ml use_ml from csct_list_items cli, csct_list_items_text clit, csct_users u1, csct_users u2, csct_pages p where u1.id=cli.user_id and u2.id=cli.ed_user_id and cli.id=clit.data_id and p.id=cli.parent_id and ";
        if (is_array($tdata_id))
            $query .= "cli.id in (" . join(", ", $tdata_id) . ")";
        else
            $query .= "cli.id=" . $tdata_id;
        $query .= " and cli.id=clit.data_id";

        if (is_array($tdata_id))
            $page_data = $this->dbh->queryFetchAll($query);
        else
            $page_data = $this->dbh->queryFetchRow($query);

        $id = 0;
        $query = "select tmpl_name from csct_templates where id=:pid";
        $sql = $this->dbh->prepare($query);
        if (is_array($tdata_id)) {
            foreach ($page_data as $key => $page) {
                if ($page['template'] > 0) {
                    $sql->execute(array(':pid' => $page['template']));
                    $page_data[$key]['tmpl_name'] = current($sql->fetch());
                    $sql->closeCursor();
                }
                elseif ($page['template'] == -1)
                    $page_data[$key]['tmpl_name'] = 'Шаблон страницы';
            }
        }
        else {
            if ($page_data['template'] > 0) {
                $sql->execute(array(':pid' => $page_data['template']));
                $page_data['tmpl_name'] = current($sql->fetch());
                $sql->closeCursor();
            }
            elseif ($page['template'] == -1)
                $page_data['tmpl_name'] = 'Шаблон страницы';
        }
        $query = "select stmpl_name from csct_subtemplates where id=:pid";
        $sql = $this->dbh->prepare($query);
        if (is_array($tdata_id)) {
            foreach ($page_data as $key => $page) {
                if ($page['subtemplate']) {
                    $sql->execute(array(':pid' => $page['subtemplate']));
                    $page_data[$key]['stmpl_name'] = current($sql->fetch());
                    $sql->closeCursor();
                }
            }
        }
        else {
            if ($page_data['subtemplate']) {
                $sql->execute(array(':pid' => $page_data['subtemplate']));
                $page_data['stmpl_name'] = current($sql->fetch());
                $sql->closeCursor();
            }
        }

        if (is_array($tdata_id)) {
            $query = "select * from csct_constants where data_id=:pid and data_type=2";
            $sql = $this->dbh->prepare($query);
            foreach ($page_data as $key => $page) {
                $sql->execute(array(':pid' => $page['id']));
                $page_data[$key]['constants'] = $sql->fetchAll();
                $sql->closeCursor();
            }
        }
        else {
            $query = "select * from csct_constants where data_id=" . $tdata_id . " and data_type=2";
            $page_data['constants'] = $this->dbh->queryFetchAll($query);
        }

        if (is_array($tdata_id)) {
            $query = "select header from csct_pages_text where data_id=:pid limit 1";
            $sql = $this->dbh->prepare($query);
            foreach ($page_data as $key => $page) {
                if ($page['plink']) {
                    $sql->execute(array(':pid' => $page['plink']));
                    $page_data[$key]['plink_name'] = current($sql->fetch());
                    $sql->closeCursor();
                }
            }
        }
        else {
            if ($page_data['plink'])
                $page_data['plink_name'] = current($this->dbh->queryFetchRow('select header from csct_pages_text where data_id=' .
                    $page_data['plink'] . ' limit 1'));
        }

        if ($get_text) {
            if (is_array($tdata_id)) {
                $query = "select * from csct_list_items_text where data_id=:pid";
                $sql = $this->dbh->prepare($query);
                $text_data = array();
                foreach ($page_data as $key => $page) {
                    $sql->execute(array(':pid' => $page['id']));
                    $result = $sql->fetchAll();
                    $sql->closeCursor();
                    foreach ($result as $item)
                        $text_data[$key][$item['lang_id']] = $item;
                }
                return array('page_data' => $page_data, 'text_data' => $text_data);
            }
            else {
                $query = "select * from csct_list_items_text where data_id=" . $tdata_id;
                $text_data = array();
                $result = $this->dbh->queryFetchAll($query);
                foreach ($result as $item)
                    $text_data[$item['lang_id']] = $item;
                return array('page_data' => $page_data, 'text_data' => $text_data);
            }
        }
        else
            return $page_data;
    }

    function get_fldname()
    {
        if (isset($_REQUEST['table']) && isset($_REQUEST['id']) && is_numeric($_REQUEST['id'])) {
            if ($_REQUEST['id'] == -1 && $_REQUEST['table'] == 'csct_templates')
                return array('id' => -1, 'text' => 'Шаблон страницы');
            else {
                if (isset($_REQUEST['data_id']))
                    $query = "select data_id id, header text from " . $_REQUEST['table'] . " where data_id=" . $_REQUEST['id'];
                else
                    $query = "select * from " . $_REQUEST['table'] . " where id=" . $_REQUEST['id'];
                return $this->dbh->queryFetchRow($query);
            }
        }
    }

    function get_fpdname()
    {
        $id = 0;
        $query = "select data_id id, header text from csct_pages_text where data_id=:id";
        $psql = $this->dbh->prepare($query);
        $psql->bindParam(':id', $id);
        $query = "select data_id id, header text from csct_list_items_text where data_id=:id";
        $lsql = $this->dbh->prepare($query);
        $lsql->bindParam(':id', $id);
        $items = explode(",", $_REQUEST['id']);
        $result = array();
        foreach ($items as $item) {
            $type = substr($item, -1);
            $id = substr($item, 0, -1);
            //list($type, $id) = explode("_", $item);
            if ($type == 0) {
                $psql->execute();
                $rslt = $psql->fetch(PDO::FETCH_ASSOC);
                $psql->closeCursor();
                $rslt['id'] .= '0';
                $result[] = $rslt;
            }
            else {
                $lsql->execute();
                $rslt = $lsql->fetch(PDO::FETCH_ASSOC);
                $lsql->closeCursor();
                $rslt['id'] .= '1';
                $result[] = $rslt;
            }
        }
        return $result;
    }

    function get_fspdname()
    {
        $id = 0;
        if ($_REQUEST['et'] == 1)
            $query = "select data_id id, header text from csct_pages_text where data_id=" . $_REQUEST['id'];
        else
            $query = "select data_id id, header text from csct_list_items_text where data_id=" . $_REQUEST['id'];
        return $this->dbh->queryFetchRow($query);
    }

    function get_susername()
    {
        $query = "select id, fio text from site_users where id=" . $_REQUEST['id'];
        return $this->dbh->queryFetchRow($query);
    }

    function get_fdname()
    {
        if (is_array($_REQUEST['id']))
            $query = "select data_id id, name text from csct_lib_content_names where data_id in (" . join(",", $_REQUEST['id']) .
                ")";
        else
            $query = "select data_id id, name text from csct_lib_content_names where data_id" . (strpos($_REQUEST['id'],
                ',') === false ? "=" . $_REQUEST['id']:" in (" . $_REQUEST['id'] . ")");
        return $this->dbh->queryFetchAll($query);

    }

    /**
     * model_cmain::get_templates()
     * 
     * @return
     */
    function get_templates($name = null)
    {
        $query = "select * from csct_templates";
        if ($name)
            $query .= " where (tmpl_name like '%" . $name . "%' or id='" . $name . "' or tmpl_cname like '%" . $name .
                "%')";
        return $this->dbh->queryFetchAll($query);
    }

    /**
     * model_cmain::get_subtemplates()
     * 
     * @return
     */
    function get_subtemplates($name = null)
    {
        $query = "select * from csct_subtemplates";
        if ($name)
            $query .= " where stmpl_name like '%" . $name . "%' or id='" . $name . "' or stmpl_cname like '%" .
                $name . "%'";

        return $this->dbh->queryFetchAll($query);
    }

    /**
     * model_cmain::get_snippets()
     * 
     * @param mixed $stype
     * @return
     */
    function get_snippets($stype = null, $name = null, $ch_status = false)
    {
        $query = "select * from csct_snippets";
        if ($stype)
            $query .= " where stype=" . $stype;
        if ($name)
            $query .= ($stype ? " and ":" where ") . " (snp_name like '%" . $name . "%' or id='" . $name .
                "' or snp_cname like '%" . $name . "%')";
        if ($ch_status)
            $query .= ($stype || $name ? " and ":" where ") . "status=" . $ch_status;

        return $this->dbh->queryFetchAll($query);
    }

    function get_module_ctrls()
    {
        $query = "select * from csct_modules_ctrl";
        $ctrl_list = $this->dbh->queryFetchAll($query);
        return $ctrl_list;
    }

    /**
     * model_cmain::get_domains()
     * 
     * @param mixed $tdata_id
     * @param integer $data_type
     * @return
     */
    function get_sites($tdata_id = null, $data_type = 0)
    {
        $query = "select * from csct_sites where status=1 order by num asc";
        $sites = $this->dbh->queryFetchAll($query);
        if ($tdata_id) {
            $qry = "select * from csct_site_links where data_type=" . $data_type . " and data_id=" . $tdata_id;
            $item_sites = $this->dbh->queryFetchAll($qry);
            $sites_id = array();
            $site_data = array();
            foreach ($item_sites as $item) {
                $sites_id[] = $item['site_id'];
                $site_data[$item['site_id']] = $item;
            }

            return array(
                'sites' => $sites,
                'site_links' => $sites_id,
                'site_data' => $site_data);
        }
        else
            return $sites;
    }

    /**
     * model_cmain::get_pages()
     * 
     * @param mixed $tdata_id
     * @param bool $check_status
     * @return
     */
    function get_pages($tdata_id = null, $check_status = false)
    {
        $query = "select cdb.*, cdbt.header from csct_pages cdb, csct_pages_text cdbt where cdb.id=cdbt.data_id";
        if ($tdata_id)
            $query .= " and cdb.id<>" . $tdata_id;
        if ($check_status !== false)
            $query .= " and cdb.status=" . $check_status;
        $query .= " group by cdb.id order by cdbt.header asc";
        return $this->dbh->queryFetchAll($query);
    }

    /**
     * model_cmain::get_page_groups()
     * 
     * @param mixed $tdata_id
     * @param bool $check_status
     * @return
     */
    function get_page_groups($tdata_id = null, $check_status = false)
    {
        $query = "select mt.*, nt.name from csct_page_groups mt, csct_page_groups_names nt where mt.id=nt.data_id";
        if ($tdata_id)
            $query .= " and mt.id<>" . $tdata_id;
        if ($check_status !== false)
            $query .= " and mt.status=" . $check_status;
        $query .= " group by mt.id";
        $pg_result = $this->dbh->queryFetchAll($query);
        return $pg_result;
    }

    /**
     * model_cmain::get_page_group()
     * 
     * @param integer $pgr_id
     * @return
     */
    function get_page_group($pgr_id = 0, $get_text = true)
    {
        $query = "select mt.*, DATE_FORMAT(mt.crtime, '%d.%m.%Y %H:%i') fcrtime, DATE_FORMAT(mt.edtime, '%d.%m.%Y %H:%i') fedtime, u1.name cr_user_name, u2.name ed_user_name, nt.name from csct_page_groups mt, csct_page_groups_names nt, csct_users u1, csct_users u2 where u1.id=mt.user_id and u2.id=mt.ed_user_id and mt.id=nt.data_id and mt.id" . (is_array
            ($pgr_id) ? " in (" . (join(", ", $pgr_id)) . ")":" =" . $pgr_id) . " group by mt.id";
        if (is_array($pgr_id))
            $page_data = $this->dbh->queryFetchAll($query);
        else
            $page_data = $this->dbh->queryFetchRow($query);

        if (is_array($pgr_id)) {
            $query = "select pg_id from csct_pgr_link where data_id=:pid";
            $sql = $this->dbh->prepare($query);
            foreach ($page_data as $key => $page) {
                $sql->execute(array(':pid' => $page['id']));
                $page_data[$key]['pg_link'] = $sql->fetchAll(PDO::FETCH_COLUMN);
                $sql->closeCursor();
            }
        }
        else {
            $query = "select pg_id from csct_pgr_link where data_id=" . $pgr_id;
            $page_data['pg_link'] = $this->dbh->query($query)->fetchAll(PDO::FETCH_COLUMN);
        }

        $query = "select dtmpl_name from csct_data_templates where id=:pid";
        $sql = $this->dbh->prepare($query);
        if (is_array($pgr_id)) {
            foreach ($page_data as $key => $page) {
                if ($page['dtmpl_id']) {
                    $sql->execute(array(':pid' => $page['dtmpl_id']));
                    $page_data[$key]['dtmpl_name'] = current($sql->fetch());
                    $sql->closeCursor();
                }
            }
        }
        else {
            if ($page_data['dtmpl_id']) {
                $sql->execute(array(':pid' => $page_data['dtmpl_id']));
                $page_data['dtmpl_name'] = current($sql->fetch());
                $sql->closeCursor();
            }
        }

        if (is_array($pgr_id)) {
            $query = "select header from csct_pages_text where data_id=:pid limit 1";
            $sql = $this->dbh->prepare($query);
            foreach ($page_data as $key => $page) {
                if ($page['plink']) {
                    $sql->execute(array(':pid' => $page['plink']));
                    $page_data[$key]['plink_name'] = current($sql->fetch());
                    $sql->closeCursor();
                }
            }
        }
        else {
            if ($page_data['plink'])
                $page_data['plink_name'] = current($this->dbh->queryFetchRow('select header from csct_pages_text where data_id=' .
                    $page_data['plink'] . ' limit 1'));
        }
        if (is_array($pgr_id)) {
            $query = "select header from csct_pages_text where data_id=:pid limit 1";
            $sql = $this->dbh->prepare($query);
            foreach ($page_data as $key => $page) {
                if ($page['parent_page']) {
                    $sql->execute(array(':pid' => $page['parent_page']));
                    $page_data[$key]['parent_page_name'] = current($sql->fetch());
                    $sql->closeCursor();
                }
            }
        }
        else {
            if ($page_data['parent_page'])
                $page_data['parent_page_name'] = current($this->dbh->queryFetchRow('select header from csct_pages_text where data_id=' .
                    $page_data['parent_page'] . ' limit 1'));
        }
        if (is_array($pgr_id)) {
            $query = "select user_id from csct_userlinks where data_id=:pid and data_type=1";
            $sql = $this->dbh->prepare($query);
            foreach ($page_data as $key => $page) {
                $sql->execute(array(':pid' => $page['id']));
                $page_data[$key]['ousers'] = $sql->fetchAll(PDO::FETCH_COLUMN);
                $sql->closeCursor();
            }
        }
        else {
            $query = "select user_id from csct_userlinks where data_id=" . $pgr_id . " and data_type=1";
            $ouresult = $this->dbh->query($query)->fetchAll(PDO::FETCH_COLUMN);
            $page_data['ousers'] = $ouresult;
        }

        if ($get_text) {
            if (is_array($pgr_id)) {

                $query = "select *, name header from csct_page_groups_names where data_id=:pid";
                $sql = $this->dbh->prepare($query);
                $text_data = array();
                foreach ($page_data as $key => $page) {
                    $sql->execute(array(':pid' => $page['id']));
                    $result = $sql->fetchAll();
                    $sql->closeCursor();
                    foreach ($result as $item)
                        $text_data[$key][$item['lang_id']] = $item;
                }
            }
            else {
                $query = "select * from csct_page_groups_names where data_id=" . $pgr_id;
                $text_data = array();
                $result = $this->dbh->queryFetchAll($query);
                foreach ($result as $item)
                    $text_data[$item['lang_id']] = $item;
            }

            return array('page_data' => $page_data, 'text_data' => $text_data);
        }
        else
            return $page_data;
    }
    /*
    function get_page_path($tdata_id)
    {
    $query = "select pt.id, ptn.header from csct_pages pt, csct_pages_text ptn where ptn.data_id=pt.id and pt.parent=:page_id group by pt.id";
    $sql = $this->dbh->prepare($query);
    $path = array();
    do {
    $sql->execute();
    $page_data = $sql->fetch();
    $sql->closeCursor();
    if ($page_data) {
    $tdata_id = $page_data['id'];
    $path[] = $page_data['header'];
    }
    else
    break;
    } while (1);
    return (join('/', $path));
    }
    */
    /**
     * model_cmain::addrqty()
     * 
     * @param mixed $tdata_id
     * @return
     */
    function addrqty($tdata_id)
    {
        $query = "select count(id) from csct_pages where address=(select address from csct_pages where id=" .
            $tdata_id . ") and parent in (select parent from csct_pages where id=" . $tdata_id . ")";
        if (app()->md)
            $query .= " and (use_md=0 or (use_md=1 and id in (select data_id from csct_site_links where data_type=0 and  site_id in (select site_id from csct_site_links where data_id=" .
                $tdata_id . "))))";
        $addrqty = current($this->dbh->queryFetchRow($query));
        return ($addrqty);
    }

    /**
     * model_cmain::get_div()
     * 
     * @param mixed $stmpl_id
     * @param mixed $parent_id
     * @param mixed $data_sql
     * @param mixed $sql
     * @param mixed $tdata_id
     * @param mixed $page_type
     * @param bool $get_text
     * @return
     */
    function get_div($stmpl_id, $parent_id, &$data_sql, &$sql, $tdata_id, $page_type, $get_text = true)
    {
        $data = array();
        $query = "select * from csct_stmpl_data where stmpl_id=" . $stmpl_id . " and parent=" . $parent_id .
            " order by num asc";
        $stmpl_data = $this->dbh->queryFetchAll($query);
        foreach ($stmpl_data as $stmpl_item) {
            $sql->execute(array($stmpl_item['id']));
            $is_inline = current($sql->fetch());
            $sql->closeCursor();
            if (!$is_inline) {
                $data_sql->execute(array($stmpl_item['id']));
                $div_data = $data_sql->fetch();
                $data_sql->closeCursor();
                if ($div_data)
                    $stmpl_item['text'] = $div_data['div_text'];
            }
            else
                $stmpl_item['inline_blocks'] = $this->get_div($stmpl_id, $stmpl_item['id'], $data_sql, $sql, $tdata_id,
                    $page_type);
            $data[] = $stmpl_item;
        }
        return $data;
    }

    /**
     * model_cmain::get_stmpl_data()
     * 
     * @param mixed $tdata_id
     * @param mixed $page_type
     * @param mixed $stmpl_id
     * @param mixed $lid
     * @return
     */
    function get_stmpl_data($tdata_id, $page_type, $stmpl_id, $lid)
    {
        $div_id = 0;
        $query = "select * from csct_stmpl_data_text where page_id=" . $tdata_id . " and page_type=" . $page_type .
            " and block_id=? and lang_id=" . $lid;
        $data_sql = $this->dbh->prepare($query);

        $query = "select count(id) from csct_stmpl_data where parent=?";
        $sql = $this->dbh->prepare($query);
        $data = array();
        $data = $this->get_div($stmpl_id, 0, $data_sql, $sql, $tdata_id, $page_type);
        return $data;
    }

    /**
     * model_cmain::get_stmpl_div_text()
     * 
     * @param mixed $div_id
     * @param mixed $tdata_id
     * @param mixed $page_type
     * @param bool $check_lang
     * @return
     */
    function get_stmpl_div_text($div_id, $tdata_id, $page_type, $check_lang = false)
    {
        $query = "select * from csct_stmpl_data where id=" . $div_id;
        $stmpl_data = $this->dbh->queryFetchRow($query);
        if ($stmpl_data['div_type'] == 0) {
            $lid = 0;
            $query = "select * from csct_stmpl_data_text where page_id=" . $tdata_id . " and page_type=" . $page_type .
                " and block_id=" . $div_id . " and lang_id=:lid";
            $data_sql = $this->dbh->prepare($query);
            $data_sql->bindParam(':lid', $lid, PDO::PARAM_INT);

            if ($page_type == 0)
                $query = "select use_ml from csct_pages where id=" . $tdata_id;
            else
                $query = "select use_ml from csct_pages where id=(select parent_id from csct_list_items where id=" .
                    $tdata_id . ")";
            $hp_data = $this->dbh->queryFetchRow($query);
            $stmpl_data['use_ml'] = $hp_data['use_ml'];
            if (app()->ml && $hp_data['use_ml']) {
                if (!$check_lang) {
                    foreach (app()->csct_langs as $lid => $lang_name) {
                        $data_sql->execute();
                        $text_data = $data_sql->fetch();
                        $data_sql->closeCursor();
                        $stmpl_data['text'][$lid] = $text_data ? $text_data['div_text']:$stmpl_data['default_text'];
                    }
                }
                else {
                    $lid = app()->lang_id;
                    $data_sql->execute();
                    $text_data = $data_sql->fetch();
                    $data_sql->closeCursor();
                    $stmpl_data['text'] = $text_data ? $text_data['div_text']:$stmpl_data['default_text'];
                }
            }
            else {
                $lid = 0;
                $data_sql->execute();
                $text_data = $data_sql->fetch();
                $data_sql->closeCursor();
                $stmpl_data['text'] = $text_data ? $text_data['div_text']:$stmpl_data['default_text'];
            }
        }
        else {
            $query = "select * from csct_stmpl_photos where page_id=" . $tdata_id . " and page_type=" . $page_type .
                " and block_id=" . $div_id;
            $result = $this->dbh->queryFetchAll($query);
            $stmpl_data['photos'] = $result;
        }
        return $stmpl_data;
    }

    /**
     * model_cmain::lp_list_smpl()
     * 
     * @param mixed $sstring
     * @param bool $no_links
     * @return
     */
    function lp_list_smpl($sstring = null, $no_links = false, $field_id = null)
    {
        $query = "select cli.id, clit.header text from csct_list_items cli, csct_list_items_text clit where cli.id=clit.data_id";
        if ($sstring)
            $query .= " and (clit.header like '%" . $sstring . "%' or cli.id='" . $sstring . "')";
        if ($no_links)
            $query .= " and cli.db_type=0";
        if ($field_id) {
            $qry = "select count(field_id) from csct_dtmpl_fdp where field_id=" . $field_id;
            $rslt = $this->dbh->queryFetchRow($qry);
            if (current($rslt))
                $query .= " and cli.id in (select item_id from csct_dtmpl_fdp where ptype=1 and field_id=" . $field_id .
                    ")";
        }
        $query .= " group by cli.id";
        return $this->dbh->queryFetchAll($query);
    }

    /**
     * model_cmain::p_list_smpl()
     * 
     * @param mixed $sstring
     * @param bool $no_links
     * @param bool $sa
     * @return
     */
    function p_list_smpl($sstring = null, $no_links = false, $sa = false, $field_id = null, $lo = null)
    {
        $query = "select p.id," . ($sa ? " p.address,":"") .
            " pt.header text from csct_pages p, csct_pages_text pt where p.id=pt.data_id";
        if ($sstring)
            $query .= " and (pt.header like '%" . $sstring . "%' or p.id='" . $sstring .
                "' or p.address like '%" . $sstring . "%')";
        if ($no_links)
            $query .= " and p.db_type<>2";
        if ($lo)
            $query .= " and p.db_type=1";
        if ($field_id) {
            $qry = "select count(field_id) from csct_dtmpl_fdp where field_id=" . $field_id;
            $rslt = $this->dbh->queryFetchRow($qry);
            if (current($rslt))
                $query .= " and p.id in (select item_id from csct_dtmpl_fdp where ptype=0 and field_id=" . $field_id .
                    ")";
        }
        $query .= " group by p.id";
        return $this->dbh->queryFetchAll($query);
    }

    /**
     * model_cmain::lp_list()
     * 
     * @param mixed $tdata_id
     * @param integer $page
     * @param integer $limit
     * @param mixed $sstring
     * @param bool $get_content
     * @param mixed $tpage_data
     * @param bool $check_status
     * @param bool $check_lang
     * @return
     */
    function lp_list($tdata_id, $page = 1, $limit = 15, $sstring = null, $get_content = false, $tpage_data = null,
        $check_status = false, $check_lang = false, $get_qty = true, $get_data = true, $incall = false)
    {
        if ($incall && $limit)
            $this->dbh->exec("update csct_users set " . $limit . " where id=" . $this->registry->user_id);
        if ($page && $limit)
            $start = ($page - 1) * $limit;
        if ($get_qty) {
            $query = "select count(distinct(cli.id)) from csct_list_items cli left join csct_list_items_text clit on cli.id=clit.data_id left join csct_list_items cli1 on cli.plink=cli1.id left join csct_list_items_text clit1 on cli1.id=clit1.data_id where cli.parent_id=" .
                $tdata_id;
            if ($sstring)
                $query .= " and (clit.header like '%" . $sstring . "%' or clit.header like '%" . $sstring .
                    "%' or cli.id='" . $sstring . "')";
            if ($check_status !== false)
                $query .= " and cli.status=" . $check_status;

            $total_recs = current($this->dbh->query($query)->fetch(PDO::FETCH_NUM));
        }
        else
            $total_recs = 0;
        if ($get_data) {
            if (!$tpage_data)
                $tpage_data = $this->get_page_data($tdata_id, false);
            if ($tpage_data['sorting'] == 0)
                $sort = "num";
            elseif ($tpage_data['sorting'] == 1)
                $sort = "dateofpub";
            elseif ($tpage_data['sorting'] == 2)
                $sort = "header";
            $order = $tpage_data['sort_reverse'] == 0 ? "asc":"desc";
            if ($tpage_data['sorting'] == 0)
                $order = "asc";

            $query = "select cli.*, DATE_FORMAT(cli.dateofpub, '%d.%m.%Y') fdateofpub, clit.header, clit1.header header1 from csct_list_items cli left join csct_list_items_text clit on cli.id=clit.data_id left join csct_list_items cli1 on cli.plink=cli1.id left join csct_list_items_text clit1 on cli1.id=clit1.data_id where cli.parent_id=" .
                $tdata_id;
            if ($sstring)
                $query .= " and (clit.header like '%" . $sstring . "%' or clit.header like '%" . $sstring . "%')";
            if ($check_status !== false)
                $query .= " and cli.status=" . $check_status;
            if ($check_lang) {
                $lang_id = (app()->ml && $tpage_data['use_ml']) ? app()->lang_id:0;
                $query .= " and clit.lang_id=" . $lang_id;
            }
            $query .= " group by cli.id order by cli" . ($sort == "header" ? "t." . $sort:"." . $sort) . " " . $order .
                ", cli" . ($sort == "header" ? "t1." . $sort:"." . $sort) . " " . $order;

            if ($page && $limit)
                $query .= " limit " . $start . ", " . $limit;
            $result = $this->dbh->queryFetchAll($query);
            foreach ($result as $key => $item)
                if ($item['db_type'] == 1)
                    $result[$key]['header'] = $item['header1'];

            if ($get_content) {

                foreach ($result as $key => $item) {
                    $result[$key]['ldata'] = $this->get_lpage($item['id'], $tdata_id);
                    if ($tpage_data['dtmpl_id_lc'])
                        $result[$key]['ldata']['dtmpl_data'] = $this->get_dtmpl_data($item['id'], 3, $tpage_data['dtmpl_id_lc']);
                }

            }
        }
        else
            $result = array();

        return array('items_qty' => $total_recs, 'data' => $result);
    }

    /**
     * model_cmain::laddrqty()
     * 
     * @param mixed $tdata_id
     * @param mixed $parent_id
     * @return
     */
    function laddrqty($tdata_id, $parent_id)
    {
        $query = "select count(*) from csct_list_items where address=(select address from csct_list_items where id=" .
            $tdata_id . ") and parent_id=" . $parent_id;
        return current($this->dbh->queryFetchRow($query));
    }

    /**
     * model_cmain::get_lpage()
     * 
     * @param mixed $tdata_id
     * @param mixed $parent_id
     * @param bool $get_text
     * @param bool $check_lang
     * @return
     */
    function get_lpage($tdata_id, $parent_id, $get_text = true, $check_lang = false)
    {
        if ($check_lang !== false)
            $gpage_data = $this->get_page_data($parent_id, false);
        $query = "select cli.*, DATE_FORMAT(cli.crtime, '%d.%m.%Y %H:%i') fcrtime, DATE_FORMAT(cli.edtime, '%d.%m.%Y %H:%i') fedtime, u1.name cr_user_name, u2.name ed_user_name, clit.header, DATE_FORMAT(cli.dateofpub, '%d.%m.%Y') fdateofpub, p.dtmpl_id_lc dtmpl_id, p.use_ml use_ml from csct_list_items cli, csct_list_items_text clit, csct_users u1, csct_users u2, csct_pages p where u1.id=cli.user_id and u2.id=cli.ed_user_id and cli.id=clit.data_id and p.id=cli.parent_id and " . (is_numeric
            ($tdata_id) ? "cli.id=" . $tdata_id:"cli.address='" . $tdata_id . "' and p.id=" . $parent_id);

        if ($check_lang !== false) {
            $lang_id = (app()->ml && $gpage_data['use_ml']) ? app()->lang_id:0;
            $query .= " and clit.lang_id=" . $lang_id;
        }
        $query .= " limit 1";
        $page_data = $this->dbh->query($query)->fetch();
        $query = "select * from csct_constants where data_id=" . $tdata_id . " and data_type=2";
        $page_data['constants'] = $this->dbh->queryFetchAll($query);
        if ($page_data['plink'])
            $page_data['plink_name'] = current($this->dbh->queryFetchRow("select header from csct_list_items_text where data_id=" .
                $page_data['plink'] . " group by data_id"));
        if ($get_text) {
            //текстовая инфа
            $query = "select * from csct_list_items_text where data_id=" . $tdata_id;
            if ($check_lang)
                $query .= " and lang_id=" . $lang_id;

            $text_data = array();
            $result = $this->dbh->query($query)->fetchAll(PDO::FETCH_ASSOC);
            foreach ($result as $item)
                $text_data[$item['lang_id']] = $item;
            $page_data['text'] = $text_data;
        }
        return $page_data;
    }

    function get_lpages($tdata_id)
    {
        $query = "select cli.*, clit.header, clit.title DATE_FORMAT(cli.dateofpub, '%d.%m.%Y') fdateofpub, p.dtmpl_id_lc dtmpl_id from csct_list_items cli, csct_list_items_text clit where cli.id=clit.data_id and cli.id" . (is_array
            ($tdata_id) ? " in (" . (join(", ", $tdata_id)) . ")":" =" . $tdata_id) . " group by cli.id";
        if (is_array($tdata_id))
            $page_data = $this->dbh->queryFetchAll($query);
        else
            $page_data = $this->dbh->query($query)->fetch();
        if (is_array($tdata_id)) {
            $query = "select header from csct_list_items_text where data_id=:pid group by data_id";
            $sql = $this->dbh->prepare($query);
            foreach ($page_data as $key => $page) {
                $sql->execute(array(':pid' => $page['plink']));
                $page_data[$key]['plink_name'] = current($sql->fetch());
                $sql->closeCursor();
            }
        }
        else {
            if ($page_data['plink'])
                $page_data['plink_name'] = current($this->dbh->queryFetchRow("select header from csct_list_items_text where data_id=" .
                    $page_data['plink'] . " group by data_id"));
        }

        return $page_data;
    }

    /**
     * model_cmain::add_page()
     * 
     * @param integer $parent
     * @return
     */
    function add_page($parent = 0)
    {
        $this->trigger_execute('beforePageCreate');
        if ($parent) {
            $parent = preg_replace('/([a-z]+)(\d*)_(\d*)/', '\1_\3', $parent);
            list($parent_type, $parent_id) = explode("_", $parent);
        }
        $query = "insert into csct_pages (user_id, ed_user_id, crtime, edtime" . ($parent ? ($parent_type ==
            'page' ? ", parent":""):"") . ", phg_mheight, num) values ('" . $this->registry->user_id . "', '" .
            $this->registry->user_id . "', NOW(), NOW()";
        if ($parent)
            if ($parent_type == 'page')
                $query .= ", '" . $parent_id . "'";

        $nquery = "select max(num) from csct_pages where ";
        if ($parent && @$parent_type == 'page')
            $nquery .= "id in (select id from csct_pages where parent=" . $parent_id . ")";
        else
            $nquery .= "parent=0";
        $nr = $this->dbh->queryFetchRow($nquery);
        $num = $nr ? current($nr) + 1:1;
        $query .= ", '150', '" . $num . "')";
        $this->dbh->exec($query);
        $page_id = $this->dbh->lastInsertId();
        if ($this->registry['user_settings']['acl'] == 1 && $this->registry->user_settings['permissions']['rlevel'] ==
            1 && $this->registry->user_settings['permissions']['only_permitted'])
            $this->dbh->exec("insert into csct_userlinks (data_type, data_id, user_id) values ('0', '" . $page_id .
                "', '" . $this->registry->user_id . "')");
        $query = "insert into csct_pages_text (data_id, lang_id, header) values ('" . $page_id .
            "', '0', 'Новая страница')";
        $this->dbh->exec($query);
        if ($parent && @$parent_type == 'pgr') {
            $nquery = "select max(num) from csct_pg_link where pg_id=" . $page_id;
            $nr = $this->dbh->queryFetchRow($nquery);
            $num = $nr ? current($nr) + 1:1;
            $query = "insert into csct_pg_link values ('', '" . $page_id . "', '" . $parent_id . "', '" . $num .
                "')";
            $this->dbh->exec($query);
        }
        $this->trigger_execute('afterPageCreate', $page_id);
        return $page_id;

    }

    /**
     * model_cmain::add_pgr()
     * 
     * @param integer $parent
     * @return
     */
    function add_pgr($parent = 0)
    {
        $this->trigger_execute('beforePageGroupCreate');
        if ($parent) {
            $parent = preg_replace('/([a-z]+)(\d*)_(\d*)/', '\1_\3', $parent);
            list($parent_type, $parent_id) = explode("_", $parent);
        }
        else
            $parent_id = 0;
        if (!$parent || ($parent && $parent_type == 'page'))
            $nquery = "select max(num) from csct_page_groups where id not in (select data_id from csct_pgr_link)";
        else
            $nquery = "select max(num) from csct_pgr_link where pg_id=" . $parent_id;
        $nr = $this->dbh->queryFetchRow($nquery);
        $num = $nr ? current($nr) + 1:1;
        $parent_page = (!$parent || ($parent && $parent_type == 'page')) ? $parent_id:0;

        $query = "insert into csct_page_groups (user_id, ed_user_id, dtmpl_id, crtime, edtime, parent_page, num, status) values ('" .
            $this->registry->user_id . "', '" . $this->registry->user_id . "', 0, NOW(), NOW(), '" . $parent_page .
            "', '" . $num . "', '1')";
        $this->dbh->exec($query);
        $page_id = $this->dbh->lastInsertId();
        if ($this->registry['user_settings']['acl'] == 1 && $this->registry->user_settings['permissions']['rlevel'] ==
            1 && $this->registry->user_settings['permissions']['only_permitted'])
            $this->dbh->exec("insert into csct_userlinks (data_type, data_id, user_id) values ('1', '" . $page_id .
                "', '" . $this->registry->user_id . "')");
        $query = "insert into csct_page_groups_names (data_id, lang_id, name) values ('" . $page_id .
            "', 0, 'Новая группа')";
        $this->dbh->exec($query);
        if ($parent && @$parent_type == 'pgr') {
            $nquery = "select max(num) from csct_pgr_link where pg_id=" . $page_id;
            $nr = $this->dbh->queryFetchRow($nquery);
            $num = $nr ? current($nr) + 1:1;
            $query = "insert into csct_pgr_link values ('', '" . $page_id . "', '" . $parent_id . "', '" . $num .
                "')";
            $this->dbh->exec($query);
        }
        $this->trigger_execute('afterPageGroupCreate', $page_id);
        return $page_id;
    }

    /**
     * model_cmain::ch_status()
     * 
     * @param mixed $table
     * @param mixed $status
     * @param mixed $id
     * @return
     */
    function ch_status($table, $status, $id)
    {
        if ($table == "csct_pages")
            $this->trigger_execute('beforePageChStatus', $id);
        elseif ($table == "csct_list_items")
            $this->trigger_execute('beforeListItemChStatus', $id);
        $query = "update " . $table . " set status=" . ($status ? 0:1) . " where id=" . $id;
        $this->dbh->exec($query);
        if ($table == "csct_pages")
            $this->trigger_execute('afterPageChStatus', $id);
        elseif ($table == "csct_list_items")
            $this->trigger_execute('afterListItemChStatus', $id);
    }

    /**
     * model_cmain::get_dtmpl_field_data()
     * 
     * @param mixed $field_id
     * @return
     */
    function get_dtmpl_field_data($field_id)
    {
        $query = "select * from csct_dtmpl_fields where id=:field_id";
        $field_sql = $this->dbh->prepare($query);
        $field_sql->bindParam(':field_id', $field_id, PDO::PARAM_INT);
        $field_sql->execute();
        $result = $field_sql->fetch();
        $field_sql->closeCursor();
    }

    /**
     * model_cmain::get_dtmpl_data()
     * 
     * @param mixed $tdata_id
     * @param mixed $data_type
     * @param mixed $dtmpl_id
     * @param mixed $dfield_id
     * @param mixed $pdata_id
     * @param bool $assoc
     * @param bool $check_lang
     * @return
     */
    function get_dtmpl_data($tdata_id, $data_type, $dtmpl_id, $dfield_id = null, $pdata_id = null, $assoc = true,
        $check_lang = false, $get_groups = false)
    {
        //запрос выборки данных
        /*
        $field_id = 0;
        $lid = 0;
        $data_query = "select distinct id, fvalue, fnvalue, DATE_FORMAT(fdvalue, '%d.%m.%Y') ffdvalue from csct_tdata_fields where lang_id=:lid and data_id='" .
        $tdata_id . "' and field_id=:field_id";
        $data_sql = $this->dbh->prepare($data_query);
        $data_sql->bindParam(':field_id', $field_id);
        $data_sql->bindParam(':lid', $lid);
        
        $data_query = "select distinct id, fvalue, fnvalue, DATE_FORMAT(fdvalue, '%d.%m.%Y') ffdvalue from csct_tgdata_fields where lang_id=:lid and data_id='" .
        $tdata_id . "' and field_id=:field_id";
        $gdata_sql = $this->dbh->prepare($data_query);
        $gdata_sql->bindParam(':field_id', $field_id);
        $gdata_sql->bindParam(':lid', $lid);
        */
        //проверка мульти-
        if ($data_type == 1) {
            $query = "select use_ml, use_md from csct_pages where id=" . $tdata_id;
            $rslt = $this->dbh->queryFetchRow($query);
            $use_ml = $rslt['use_ml'];
            $use_md = $rslt['use_md'];
        }
        elseif ($data_type == 3) {
            $query = "select use_ml, use_md from csct_pages where id=" . $pdata_id;
            $rslt = $this->dbh->queryFetchRow($query);
            $use_ml = $rslt['use_ml'];
            $use_md = $rslt['use_md'];
        }
        else {
            $use_ml = app()->ml;
            $use_md = app()->md;
        }

        //готовим запрос данных
        $data_query = "select distinct id, fvalue, fnvalue, DATE_FORMAT(fdvalue, '%d.%m.%Y') ffdvalue, DATE_FORMAT(fdvalue, '%H:%i:%s') ftfdvalue from csct_tdata_fields where lang_id=:lid and data_id='" .
            $tdata_id . "' and field_id=:field_id";
        $data_sql = $this->dbh->prepare($data_query);
        $data_sql->bindParam(':field_id', $field_id);
        $data_sql->bindParam(':lid', $lid);

        $dp_query = "select p.data_id id, p.header from csct_pages_text p where p.data_id in (select item_id from csct_dp_links where field_id=:field_id and data_id='" .
            $tdata_id . "' and ltype=0) group by p.data_id";
        $dp_sql = $this->dbh->prepare($dp_query);
        $dp_sql->bindParam(':field_id', $field_id, PDO::PARAM_INT);

        $user_id = 0;
        $uquery = "select * from site_users where id=:user_id";
        $usql = $this->dbh->prepare($uquery);
        $usql->bindParam(':user_id', $user_id, PDO::PARAM_INT);

        $query = "select site_id from csct_ds_links where field_id=:field_id and data_id='" . $tdata_id .
            "'";
        $siteSql = $this->dbh->prepare($query);
        $siteSql->bindParam(':field_id', $field_id, PDO::PARAM_INT);

        $dplc_query = "select p.data_id id, p.header from csct_list_items_text p where p.data_id in (select item_id from csct_dp_links where field_id=:field_id and data_id='" .
            $tdata_id . "' and ltype=1) group by p.data_id";
        $dplc_sql = $this->dbh->prepare($dplc_query);
        $dplc_sql->bindParam(':field_id', $field_id, PDO::PARAM_INT);

        $value = 0;
        $fsel_query = "select cname, name from csct_dtmpl_fsel where id=:fvalue";
        $fsel_sql = $this->dbh->prepare($fsel_query);
        $fsel_sql->bindParam(':fvalue', $value, PDO::PARAM_INT);

        //получаем информацию о структуре шаблона
        $dmodel = app()->load_model('common' . DIRSEP . 'dtemplates', 'model_dtemplates');
        $dtmpl_data = $dmodel->get_dtmpl_structure($dtmpl_id, $dfield_id, $assoc);

        foreach ($dtmpl_data['fields'] as $key => $field) {
            $field_id = $field['id'];
            $lid = 0;
            if ($field['ftype'] == 0) {
                if ($field['is_ml'] && app()->ml && $use_ml && !$check_lang) {
                    foreach (app()->csct_langs as $lid => $lang_name) {
                        $data_sql->execute();
                        $result = $field['multi'] ? $data_sql->fetchAll():$data_sql->fetch();
                        $data_sql->closeCursor();
                        if (!$field['multi']) {
                            $field_value = !empty($result['fvalue']) ? $result['fvalue']:$field['default_value'];
                            $dtmpl_data['fields'][$key]['field_value'][$lid] = $field_value;
                        }
                        else {
                            foreach ($result as $item) {
                                $field_value = !empty($item['fvalue']) ? $item['fvalue']:$field['default_value'];
                                $dtmpl_data['fields'][$key]['field_value'][$lid][$item['id']] = $field_value;
                            }
                        }

                    }
                }
                else {
                    if ($check_lang)
                        $lid = app()->lang_id;
                    $data_sql->execute();
                    $result = $field['multi'] ? $data_sql->fetchAll():$data_sql->fetch();
                    $data_sql->closeCursor();
                    if (!$field['multi']) {
                        $field_value = !empty($result['fvalue']) ? $result['fvalue']:$field['default_value'];
                        $dtmpl_data['fields'][$key]['field_value'] = $field_value;
                    }
                    else {
                        foreach ($result as $item) {
                            $field_value = !empty($item['fvalue']) ? $item['fvalue']:$field['default_value'];
                            $dtmpl_data['fields'][$key]['field_value'][$item['id']] = $field_value;
                        }
                    }
                }
            }
            elseif ($field['ftype'] == 9) {
                $data_sql->execute();
                $result = $data_sql->fetch();
                $data_sql->closeCursor();
                $user_id = intval($result['fnvalue']);
                $usql->execute();
                $dtmpl_data['fields'][$key]['field_value'] = $usql->fetch();
                $usql->closeCursor();
            }
            elseif ($field['ftype'] == 10) {
                $siteSql->execute();
                $result = $siteSql->fetchAll(PDO::FETCH_COLUMN);
                $siteSql->closeCursor();
                $dtmpl_data['fields'][$key]['field_value'] = $result;
            }
            elseif ($field['ftype'] == 3 || $field['ftype'] == 4 || $field['ftype'] == 5) {
                $data_sql->execute();
                $result = $field['multi'] && $field['ftype'] == 3 ? $data_sql->fetchAll():$data_sql->fetch();
                $data_sql->closeCursor();

                if (!$field['multi']) {
                    $field_value = !empty($result['fnvalue']) ? $result['fnvalue']:$field['default_value'];
                    $value = (($field_value * 100 % 100) == 0) ? ($field_value * 100) / 100:$field_value;
                    $dtmpl_data['fields'][$key]['field_value'] = $value;
                }
                else {
                    foreach ($result as $item) {
                        $field_value = !empty($item['fnvalue']) ? $item['fnvalue']:$field['default_value'];
                        $value = (($field_value * 100 % 100) == 0) ? ($field_value * 100) / 100:$field_value;
                        $dtmpl_data['fields'][$key]['field_value'][$item['id']] = $value;
                    }
                }

                if ($field['ftype'] == 5) {
                    $fsel_sql->execute();
                    $fsvalue = $fsel_sql->fetch();
                    $fsel_sql->closeCursor();
                    if ($fsvalue) {
                        $dtmpl_data['fields'][$key]['field_value_text'] = $fsvalue['name'];
                        $dtmpl_data['fields'][$key]['field_value_cname'] = $fsvalue['cname'];
                    }
                }
            }
            elseif ($field['ftype'] == 7) {
                $data_sql->execute();
                $result = $field['multi'] ? $data_sql->fetchAll():$data_sql->fetch();
                $data_sql->closeCursor();
                $field_value = !empty($result['ffdvalue']) ? $result['ffdvalue']:$field['default_value'];
                $field_value_time = !empty($result['ftfdvalue']) ? $result['ftfdvalue']:$field['default_value'];
                $date = $field_value ? $field_value:date("d.m.Y", time());
                $time = $field_value_time ? $field_value_time:date("H:i:s", time());

                if (!$field['multi']) {
                    $field_value = !empty($result['ffdvalue']) ? $result['ffdvalue']:$field['default_value'];
                    $field_value_time = !empty($result['ftfdvalue']) ? $result['ftfdvalue']:$field['default_value'];
                    $date = $field_value ? $field_value:date("d.m.Y", time());
                    $time = $field_value_time ? $field_value_time:date("H:i:s", time());
                    $dtmpl_data['fields'][$key]['field_value'] = $date;
                    $dtmpl_data['fields'][$key]['field_value_time'] = $time;
                }
                else {
                    foreach ($result as $item) {
                        $field_value = !empty($item['ffdvalue']) ? $item['ffdvalue']:$field['default_value'];
                        $field_value_time = !empty($item['ftfdvalue']) ? $item['ftfdvalue']:$field['default_value'];
                        $date = $field_value ? $field_value:date("d.m.Y", time());
                        $time = $field_value_time ? $field_value_time:date("H:i:s", time());
                        $dtmpl_data['fields'][$key]['field_value'][$item['id']] = $date;
                        $dtmpl_data['fields'][$key]['field_value_time'][$item['id']] = $time;
                    }
                }

            }
            elseif ($field['ftype'] == 1) {
                $data_sql->execute();
                $result = $field['multi'] ? $data_sql->fetchAll():$data_sql->fetch();
                $data_sql->closeCursor();
                if (!$field['multi']) {
                    $field_value = !empty($result['fvalue']) ? $result['fvalue']:$field['default_value'];
                    $dtmpl_data['fields'][$key]['field_value'] = $field_value;
                }
                else {
                    foreach ($result as $item) {
                        $field_value = !empty($item['fvalue']) ? $item['fvalue']:$field['default_value'];
                        $dtmpl_data['fields'][$key]['field_value'][$item['id']] = $field_value;
                    }
                }
            }
            elseif ($field['ftype'] == 2 || $field['ftype'] == 6) {
                $fresult = $this->get_dtmpl_flib_data($tdata_id, $field, $check_lang);
                $dtmpl_data['fields'][$key]['qlresult'] = $fresult;
                if ($field['ftype'] == 6)
                    $dtmpl_data['fields'][$key]['inid'] = $this->get_dtmpl_plib_data($tdata_id, $field);
            }
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

                $dtmpl_data['fields'][$key]['field_value'] = $field_value;
            }
        }
        if ($dtmpl_data['groups'] && $get_groups) {
            $group_id = 0;
            $data_query = "select distinct id, fvalue, fnvalue, DATE_FORMAT(fdvalue, '%d.%m.%Y') ffdvalue, DATE_FORMAT(fdvalue, '%H:%i:%s') ftfdvalue from csct_tgdata_fields where lang_id=:lid and data_id='" .
                $tdata_id . "' and field_id=:field_id and group_id=:group_id";
            $gdata_sql = $this->dbh->prepare($data_query);
            $gdata_sql->bindParam(':field_id', $field_id);
            $gdata_sql->bindParam(':lid', $lid);
            $gdata_sql->bindParam(':group_id', $group_id);

            $dp_query = "select p.data_id id, p.header from csct_pages_text p where p.data_id in (select item_id from csct_dgp_links where field_id=:field_id and data_id='" .
                $tdata_id . "' and ltype=0 and group_id=:group_id) group by p.data_id";
            $dgp_sql = $this->dbh->prepare($dp_query);
            $dgp_sql->bindParam(':field_id', $field_id, PDO::PARAM_INT);
            $dgp_sql->bindParam(':group_id', $group_id, PDO::PARAM_INT);

            $dplc_query = "select p.data_id id, p.header from csct_list_items_text p where p.data_id in (select item_id from csct_dgp_links where field_id=:field_id and data_id='" .
                $tdata_id . "' and ltype=1 and group_id=:group_id) group by p.data_id";
            $dgplc_sql = $this->dbh->prepare($dplc_query);
            $dgplc_sql->bindParam(':field_id', $field_id, PDO::PARAM_INT);
            $dgplc_sql->bindParam(':group_id', $group_id, PDO::PARAM_INT);

            $query = "select site_id from csct_ds_links where field_id=:field_id and group_id=:group_id and data_id='" .
                $tdata_id . "'";
            $siteGSql = $this->dbh->prepare($query);
            $siteGSql->bindParam(':field_id', $field_id, PDO::PARAM_INT);
            $siteGSql->bindParam(':group_id', $group_id, PDO::PARAM_INT);

            $groups_data = $dmodel->get_dtmpl_groups($dtmpl_id, true);
            $query = "select * from csct_tdata_groups mt where mt.dtmpl_id=" . $dtmpl_id . " and mt.data_id=" .
                $tdata_id . " order by id asc";
            $gr_data = $this->dbh->queryFetchAll($query);
            if ($gr_data) {
                foreach ($gr_data as $key => $tgroup) {
                    $group_id = $tgroup['id'];
                    $gr_data[$key]['group_data'] = $groups_data[$tgroup['group_id']]['group_data'];
                    foreach ($groups_data[$tgroup['group_id']]['group_data']['fields'] as $gkey => $field) {
                        $field_id = $field['id'];
                        $lid = 0;
                        if ($field['ftype'] == 0) {
                            if ($field['is_ml'] && app()->ml && $use_ml && !$check_lang) {
                                foreach (app()->csct_langs as $lid => $lang_name) {
                                    $gdata_sql->execute();
                                    $result = $field['multi'] ? $gdata_sql->fetchAll():$gdata_sql->fetch();
                                    $gdata_sql->closeCursor();
                                    if (!$field['multi']) {
                                        $field_value = !empty($result['fvalue']) ? $result['fvalue']:$field['default_value'];
                                        $gr_data[$key]['group_data']['fields'][$gkey]['field_value'][$lid] = $field_value;
                                    }
                                    else {
                                        foreach ($result as $item) {
                                            $field_value = !empty($item['fvalue']) ? $item['fvalue']:$field['default_value'];
                                            $gr_data[$key]['group_data']['fields'][$gkey]['field_value'][$lid][$item['id']] = $field_value;
                                        }
                                    }

                                }
                            }
                            else {
                                if ($check_lang)
                                    $lid = app()->lang_id;
                                $gdata_sql->execute();
                                $result = $field['multi'] ? $gdata_sql->fetchAll():$gdata_sql->fetch();
                                $gdata_sql->closeCursor();
                                if (!$field['multi']) {
                                    $field_value = !empty($result['fvalue']) ? $result['fvalue']:$field['default_value'];
                                    $gr_data[$key]['group_data']['fields'][$gkey]['field_value'] = $field_value;
                                }
                                else {
                                    foreach ($result as $item) {
                                        $field_value = !empty($item['fvalue']) ? $item['fvalue']:$field['default_value'];
                                        $gr_data[$key]['group_data']['fields'][$gkey]['field_value'][$item['id']] = $field_value;
                                    }
                                }
                            }
                        }
                        elseif ($field['ftype'] == 9) {
                            $gdata_sql->execute();
                            $result = $gdata_sql->fetch();
                            $gdata_sql->closeCursor();
                            $user_id = intval($result['fnvalue']);
                            $usql->execute();
                            $gr_data[$key]['group_data']['fields'][$gkey]['field_value'] = $usql->fetch();
                            $usql->closeCursor();
                        }
                        elseif ($field['ftype'] == 10) {
                            $siteGSql->execute();
                            $result = $siteGSql->fetchAll(PDO::FETCH_COLUMN);
                            $siteGSql->closeCursor();
                            $gr_data[$key]['group_data']['fields'][$gkey]['field_value'] = $result;
                        }
                        elseif ($field['ftype'] == 3 || $field['ftype'] == 4 || $field['ftype'] == 5) {
                            $gdata_sql->execute();
                            $result = $field['multi'] && $field['ftype'] == 3 ? $gdata_sql->fetchAll():$gdata_sql->fetch();
                            $gdata_sql->closeCursor();

                            if (!$field['multi']) {
                                $field_value = !empty($result['fnvalue']) ? $result['fnvalue']:$field['default_value'];
                                $value = (($field_value * 100 % 100) == 0) ? ($field_value * 100) / 100:$field_value;
                                $gr_data[$key]['group_data']['fields'][$gkey]['field_value'] = $value;
                            }
                            else {
                                foreach ($result as $item) {
                                    $field_value = !empty($item['fnvalue']) ? $item['fnvalue']:$field['default_value'];
                                    $value = (($field_value * 100 % 100) == 0) ? ($field_value * 100) / 100:$field_value;
                                    $gr_data[$key]['group_data']['fields'][$gkey]['field_value'][$item['id']] = $value;
                                }
                            }

                            if ($field['ftype'] == 5) {
                                $fsel_sql->execute();
                                $fsvalue = $fsel_sql->fetch();
                                $fsel_sql->closeCursor();
                                if ($fsvalue) {
                                    $gr_data[$key]['group_data']['fields'][$gkey]['field_value_text'] = $fsvalue['name'];
                                    $gr_data[$key]['group_data']['fields'][$gkey]['field_value_cname'] = $fsvalue['cname'];
                                }
                            }
                        }
                        elseif ($field['ftype'] == 7) {
                            $gdata_sql->execute();
                            $result = $field['multi'] ? $gdata_sql->fetchAll():$gdata_sql->fetch();
                            $gdata_sql->closeCursor();
                            $field_value = !empty($result['ffdvalue']) ? $result['ffdvalue']:$field['default_value'];
                            $field_value_time = !empty($result['ftfdvalue']) ? $result['ftfdvalue']:$field['default_value'];
                            $date = $field_value ? $field_value:date("d.m.Y", time());
                            $time = $field_value_time ? $field_value_time:date("H:i:s", time());

                            if (!$field['multi']) {
                                $field_value = !empty($result['ffdvalue']) ? $result['ffdvalue']:$field['default_value'];
                                $field_value_time = !empty($result['ftfdvalue']) ? $result['ftfdvalue']:$field['default_value'];
                                $date = $field_value ? $field_value:date("d.m.Y", time());
                                $time = $field_value_time ? $field_value_time:date("H:i:s", time());
                                $gr_data[$key]['group_data']['fields'][$gkey]['field_value'] = $date;
                                $gr_data[$key]['group_data']['fields'][$gkey]['field_value_time'] = $time;
                            }
                            else {
                                foreach ($result as $item) {
                                    $field_value = !empty($item['ffdvalue']) ? $item['ffdvalue']:$field['default_value'];
                                    $field_value_time = !empty($item['ftfdvalue']) ? $item['ftfdvalue']:$field['default_value'];
                                    $date = $field_value ? $field_value:date("d.m.Y", time());
                                    $time = $field_value_time ? $field_value_time:date("H:i:s", time());
                                    $gr_data[$key]['group_data']['fields'][$gkey]['field_value'][$item['id']] = $date;
                                    $gr_data[$key]['group_data']['fields'][$gkey]['field_value_time'][$item['id']] = $time;
                                }
                            }

                        }
                        elseif ($field['ftype'] == 1) {
                            $gdata_sql->execute();
                            $result = $field['multi'] ? $gdata_sql->fetchAll():$gdata_sql->fetch();
                            $gdata_sql->closeCursor();
                            if (!$field['multi']) {
                                $field_value = !empty($result['fvalue']) ? $result['fvalue']:$field['default_value'];
                                $gr_data[$key]['group_data']['fields'][$gkey]['field_value'] = $field_value;
                            }
                            else {
                                foreach ($result as $item) {
                                    $field_value = !empty($item['fvalue']) ? $item['fvalue']:$field['default_value'];
                                    $gr_data[$key]['group_data']['fields'][$gkey]['field_value'][$item['id']] = $field_value;
                                }
                            }
                        }
                        elseif ($field['ftype'] == 2 || $field['ftype'] == 6) {
                            $fresult = $this->get_dtmpl_gflib_data($tdata_id, $field, $check_lang, $tgroup['id']);
                            $gr_data[$key]['group_data']['fields'][$gkey]['qlresult'] = $fresult;
                            if ($field['ftype'] == 6)
                                $gr_data[$key]['group_data']['fields'][$gkey]['inid'] = $this->get_dtmpl_gplib_data($tdata_id, $field,
                                    $tgroup['id']);
                        }
                        elseif ($field['ftype'] == 8) {
                            $field_value = array();
                            $dgp_sql->execute();
                            $page_list = $dgp_sql->fetchAll();
                            $dgp_sql->closeCursor();

                            $dgplc_sql->execute();
                            $lc_list = $dgplc_sql->fetchAll();
                            $dgplc_sql->closeCursor();
                            foreach ($page_list as $page)
                                $field_value[] = array('id' => '0_' . $page['id'], 'header' => $page['header']);
                            foreach ($lc_list as $page)
                                $field_value[] = array('id' => '1_' . $page['id'], 'header' => $page['header']);

                            $gr_data[$key]['group_data']['fields'][$gkey]['field_value'] = $field_value;
                        }
                    }
                }

            }
            $dtmpl_data['groups']['groups_data'] = $gr_data;

        }

        return $dtmpl_data;
    }

    /**
     * model_cmain::get_dtmpl_flib_data()
     * 
     * @param mixed $tdata_id
     * @param mixed $field
     * @param bool $check_lang
     * @return
     */
    function get_dtmpl_flib_data($tdata_id, $field, $check_lang = false)
    {
        $qlsel = "select distinct lc.address, ofl.num, lcn.name as itemname, lib.name as libname, lib.id as lib_id, lib.dtmpl_id as lib_dtmpl_id, ofl.id as item_id, ofl.item_id lcid from csct_lib_content lc, csct_lib_content_names lcn, csct_library lib, csct_tdata_flib ofl where lc.id=lcn.data_id and ofl.data_id=" .
            $tdata_id . " and ofl.flib_id='" . $field['id'] . "' and lc.ref_id=lib.id and lc.ref_id in (" . $field['libs']['libs'] .
            ") and ofl.item_id=lc.id and lcn.lang_id='" . (!$check_lang ? app()->lang_main:app()->lang_id) .
            "' and lc.status=1 order by ofl.num asc";

        return $this->dbh->queryFetchAll($qlsel);
    }

    function get_dtmpl_gflib_data($tdata_id, $field, $check_lang = false, $group_id = 0)
    {
        $qlsel = "select distinct lc.address, ofl.num, lcn.name as itemname, lib.name as libname, lib.id as lib_id, lib.dtmpl_id as lib_dtmpl_id, ofl.id as item_id, ofl.item_id lcid from csct_lib_content lc, csct_lib_content_names lcn, csct_library lib, csct_tgdata_flib ofl where ofl.group_id=" .
            $group_id . " and lc.id=lcn.data_id and ofl.data_id=" . $tdata_id . " and ofl.flib_id='" . $field['id'] .
            "' and lc.ref_id=lib.id and lc.ref_id in (" . $field['libs']['libs'] .
            ") and ofl.item_id=lc.id and lcn.lang_id='" . (!$check_lang ? app()->lang_main:app()->lang_id) .
            "' and lc.status=1 order by ofl.num asc";

        return $this->dbh->queryFetchAll($qlsel);
    }

    /**
     * model_cmain::get_dtmpl_plib_data()
     * 
     * @param mixed $tdata_id
     * @param mixed $field
     * @return
     */
    function get_dtmpl_plib_data($tdata_id, $field)
    {

        $d1_query = "select * from csct_dtmpl_plib where field_id=" . $field['id'];
        $d1 = $this->dbh->queryFetchRow($d1_query);
        $d2_query = "select distinct tdfl.item_id from csct_tdata_flib tdfl where tdfl.flib_id=" . $d1['data_field'] .
            " and tdfl.data_id in (select distinct item_id from csct_tdata_flib where flib_id=" . $d1['parent_field'] .
            " and data_id=" . $tdata_id . ")";
        $inid = $this->dbh->query($d2_query)->fetchAll(PDO::FETCH_COLUMN);
        if (!$inid)
            $inid = 0;
        else
            $inid = join(',', $inid);
        return $inid;
    }

    function get_dtmpl_gplib_data($tdata_id, $field, $group_id)
    {

        $d1_query = "select * from csct_dtmpl_plib where field_id=" . $field['id'];
        $d1 = $this->dbh->queryFetchRow($d1_query);
        $d2_query = "select distinct tdfl.item_id from csct_tdata_flib tdfl where tdfl.flib_id=" . $d1['data_field'] .
            " and tdfl.data_id in (select distinct item_id from csct_tgdata_flib where flib_id=" . $d1['parent_field'] .
            " and data_id=" . $tdata_id . " and group_id=" . $group_id . ")";
        $inid = $this->dbh->query($d2_query)->fetchAll(PDO::FETCH_COLUMN);
        if (!$inid)
            $inid = 0;
        else
            $inid = join(',', $inid);
        return $inid;
    }

    /**
     * model_cmain::get_dtmpl_fsel()
     * 
     * @param mixed $field_id
     * @return
     */
    function get_dtmpl_fsel($field_id)
    {
        $fsel_query = "select * from csct_dtmpl_fsel where field_id='" . $field_id . "'";
        return $this->dbh->queryFetchAll($fsel_query);
    }

    /**
     * model_cmain::get_all_table()
     * 
     * @param mixed $table
     * @param mixed $domains
     * @param mixed $ref_id
     * @param integer $page
     * @param integer $limit
     * @return
     */
    function get_all_table($table, $domains = null, $ref_id = null, $page = 1, $limit = 20)
    {
        $query = "select count(distinct mt.id) from " . $table . " mt";
        $conditions = array();
        if ($ref_id)
            $conditions[] = "ref_id='" . $ref_id . "'";
        if ($conditions)
            $query .= " where " . join(" and ", $conditions);
        $total_recs = current($this->dbh->queryFetchRow($query));

        $start = ($page - 1) * $limit;
        $sel_query = "select distinct mt.*, nt.name from " . $table . " mt, " . $table . "_names nt";
        $sel_query .= " where mt.id=nt.data_id";
        if ($conditions)
            $sel_query .= " and " . join(" and ", $conditions);
        $sel_query .= " group by mt.id order by num asc";
        $sel_query .= " limit " . $start . ", " . $limit;
        return array('data_qty' => $total_recs, 'data' => $this->dbh->queryFetchAll($sel_query));
    }

    /**
     * model_cmain::mselect()
     * 
     * @return
     */
    function mselect()
    {
        $query = "select lcn.name, lc.id, lib.name as libname, lib.id as lib_id from csct_lib_content_names lcn, csct_lib_content lc, csct_library lib where lc.ref_id=lib.id and lc.id=lcn.data_id";
        if (!isset($_REQUEST['skipst']))
            $query .= " and lc.status=1";
        if (isset($_REQUEST['lib_str']))
            $query .= " and lc.ref_id in (" . $_REQUEST['lib_str'] . ")";
        if (isset($_REQUEST['inid']))
            $query .= " and lc.id in (" . $_REQUEST['inid'] . ")";
        $query .= " and (lcn.name like '%" . $_REQUEST['q'] . "%' or lc.id = '" . $_REQUEST['q'] . "')";
        $query .= " group by lc.id";
        $result = $this->dbh->prepare($query);
        $result->execute();
        $data = $result->fetchAll(PDO::FETCH_ASSOC);
        $result->closeCursor();
        return $data;
    }

    /**
     * model_cmain::add_ofl()
     * 
     * @return
     */
    function add_ofl()
    {
        //if ($_POST['lib_id'] == 0)
        //    list($lib_id, $item_id) = explode("_", $_POST['data_id']);
        //else {
        $lib_id = $_POST['lib_id'];
        $item_id = $_POST['data_id'];
        //}
        if (is_array($item_id)) {
            foreach ($item_id as $item) {
                if (isset($_POST['group_id']) && $_POST['group_id'])
                    $query = "select * from csct_tgdata_flib where group_id=" . $_POST['group_id'] . " and data_id=" . $_POST['tdata_id'] .
                        " and flib_id=" . $_POST['flib_id'] . " and item_id=" . $item;
                else
                    $query = "select * from csct_tdata_flib where data_id=" . $_POST['tdata_id'] . " and flib_id=" . $_POST['flib_id'] .
                        " and item_id=" . $item;
                if (!$this->dbh->query($query)->fetch()) {
                    if (isset($_POST['group_id']) && $_POST['group_id'])
                        $query = "select max(num) from csct_tgdata_flib where group_id=" . $_POST['group_id'] .
                            " and data_id=" . $_POST['tdata_id'] . " and flib_id=" . $_POST['flib_id'] . " and lib_id=" . $lib_id;
                    else
                        $query = "select max(num) from csct_tdata_flib where data_id=" . $_POST['tdata_id'] .
                            " and flib_id=" . $_POST['flib_id'] . " and lib_id=" . $lib_id;
                    $nqr = $this->dbh->query($query)->fetch();
                    $num = $nqr ? (current($nqr) + 1):1;
                    if (isset($_POST['group_id']) && $_POST['group_id'])
                        $query = "insert into csct_tgdata_flib values ('', '" . $num . "', '" . $_POST['tdata_id'] . "', '" .
                            $_POST['group_id'] . "', '" . $_POST['flib_id'] . "', '" . $lib_id . "', '" . $item . "')";
                    else
                        $query = "insert into csct_tdata_flib values ('', '" . $num . "', '" . $_POST['tdata_id'] . "', '" .
                            $_POST['flib_id'] . "', '" . $lib_id . "', '" . $item . "')";
                    $this->dbh->exec($query);
                }
            }
        }
        else {
            if (isset($_POST['group_id']) && $_POST['group_id'])
                $query = "select * from csct_tgdata_flib where group_id=" . $_POST['group_id'] . " and data_id=" . $_POST['tdata_id'] .
                    " and flib_id=" . $_POST['flib_id'] . " and lib_id=" . $lib_id . " and item_id=" . $item_id;
            else
                $query = "select * from csct_tdata_flib where data_id=" . $_POST['tdata_id'] . " and flib_id=" . $_POST['flib_id'] .
                    " and lib_id=" . $lib_id . " and item_id=" . $item_id;
            if (!$this->dbh->query($query)->fetch()) {
                if (isset($_POST['group_id']) && $_POST['group_id'])
                    $query = "select max(num) from csct_tgdata_flib where group_id=" . $_POST['group_id'] .
                        " and data_id=" . $_POST['tdata_id'] . " and flib_id=" . $_POST['flib_id'] . " and lib_id=" . $lib_id;
                else
                    $query = "select max(num) from csct_tdata_flib where data_id=" . $_POST['tdata_id'] .
                        " and flib_id=" . $_POST['flib_id'] . " and lib_id=" . $lib_id;
                $nqr = $this->dbh->query($query)->fetch();
                $num = $nqr ? (current($nqr) + 1):1;
                if (isset($_POST['group_id']) && $_POST['group_id'])
                    $query = "insert into csct_tgdata_flib values ('', '" . $num . "', '" . $_POST['tdata_id'] . "', '" .
                        $_POST['group_id'] . "', '" . $_POST['flib_id'] . "', '" . $lib_id . "', '" . $item_id . "')";
                else
                    $query = "insert into csct_tdata_flib values ('', '" . $num . "', '" . $_POST['tdata_id'] . "', '" .
                        $_POST['flib_id'] . "', '" . $lib_id . "', '" . $item_id . "')";
                $this->dbh->exec($query);
            }
        }
        if (isset($_POST['group_id']) && $_POST['group_id']) {
            $result = $this->get_dtmpl_data($_POST['tdata_id'], $_POST['data_type'], $_POST['dtmpl_id'], $_POST['flib_id'], null, false, false, true);
            foreach ($result['groups']['groups_data'] as $groupData) {
                if ($groupData['id'] == $_POST['group_id']) {
                    foreach ($groupData['group_data']['fields'] as $field) {
                        if ($field['id'] == $_POST['flib_id'])
                            return $field;
                    }
                }
            }
        }
        else {

            $result = $this->get_dtmpl_data($_POST['tdata_id'], $_POST['data_type'], $_POST['dtmpl_id'], $_POST['flib_id'], null, false);
            return $result['fields'][0];

        }
    }

    /**
     * model_cmain::del_ofl()
     * 
     * @return
     */
    function del_ofl()
    {
        $query = "delete from " . (isset($_POST['group_id']) && $_POST['group_id'] ? "csct_tgdata_flib":
            "csct_tdata_flib") . " where id='" . $_POST['item_id'] . "'";
        $this->dbh->exec($query);

        if (isset($_POST['group_id']) && $_POST['group_id']) {
            $result = $this->get_dtmpl_data($_POST['tdata_id'], $_POST['data_type'], $_POST['dtmpl_id'], $_POST['flib_id'], null, false, false, true);
            foreach ($result['groups']['groups_data'] as $groupData) {
                if ($groupData['id'] == $_POST['group_id']) {
                    foreach ($groupData['group_data']['fields'] as $field) {
                        if ($field['id'] == $_POST['flib_id'])
                            return $field;
                    }
                }
            }
        }
        else {
            $query = "delete from csct_pic_link where flib_id=" . $_POST['item_id'];
            $this->dbh->exec($query);
            $result = $this->get_dtmpl_data($_POST['tdata_id'], $_POST['data_type'], $_POST['dtmpl_id'], $_POST['flib_id'], null, false);
            return $result['fields'][0];

        }

    }

    /**
     * model_cmain::get_lfdata()
     * 
     * @param mixed $field_data
     * @return
     */
    function get_lfdata($field_data)
    {
        $link_data = array();
        $link_id = array();
        $is_link = 0;
        if ($field_data['ftype'] == 2) {
            $query = "select * from csct_dtmpl_plib where parent_field=" . $_POST['flib_id'];
            $rslt = $this->dbh->query($query)->fetchAll();
            if ($rslt) {
                $is_link = 1;
                foreach ($rslt as $item) {
                    if (isset($_POST['group_id']) && $_POST['group_id']) {
                        $result = $this->get_dtmpl_data($_POST['tdata_id'], $_POST['data_type'], $_POST['dtmpl_id'], $item['field_id'], null, false, false, true);
                        foreach ($result['groups']['groups_data'] as $groupData) {
                            if ($groupData['id'] == $_POST['group_id']) {
                                foreach ($groupData['group_data']['fields'] as $field) {
                                    if ($field['id'] == $item['field_id'])
                                        $field_result = $field;
                                }
                            }
                        }

                    }
                    else {
                        $result = $this->get_dtmpl_data($_POST['tdata_id'], $_POST['data_type'], $_POST['dtmpl_id'], $item['field_id'], null, false);
                        $field_result = $result['fields'][0];
                    }

                    app()->view()->set('tdata_id', $_POST['tdata_id']);
                    if (isset($_POST['group_id']))
                        app()->view()->set('group_id', $_POST['group_id']);
                    app()->view()->set('tdata_type', $_POST['data_type']);
                    app()->view()->set('field', $field_result);
                    app()->view()->set('return', 1);
                    app()->view()->show('mltselect', 'content');
                    $link_data[] = $this->registry['sOut'];
                    $link_id[] = $item['field_id'];
                }
            }
        }
        return array(
            'is_link' => $is_link,
            'link_data' => $link_data,
            'link_id' => $link_id);
    }

    /**
     * model_cmain::msa()
     * 
     * @param integer $page
     * @param integer $limit
     * @param mixed $lib_str
     * @param mixed $inid
     * @param mixed $qss
     * @param bool $pure_id
     * @param bool $check_status
     * @return
     */
    function msa($page = 1, $limit = 20, $lib_str = null, $inid = null, $qss = null, $pure_id = false, $check_status = false,
        $pp = false, $group_id = null)
    {
        $query = "select count(lc.id) from csct_lib_content lc, csct_lib_content_names lcn, csct_library lib where lc.id=lcn.data_id and lc.ref_id=lib.id";
        if ($lib_str)
            $query .= " and lc.ref_id in (" . $lib_str . ")";
        if ($inid)
            $query .= " and lc.id in (" . $inid . ")";
        if ($qss)
            $query .= " and lcn.name like '%" . $qss . "%'";
        //$query .= " group by id order by lc.num asc";
        if ($check_status !== false)
            $query .= " and lc.status=" . $check_status;
        $total_recs = current($this->dbh->queryFetchRow($query));

        $start = ($page - 1) * $limit;
        $query = "select distinct lcn.name, lc.*" . (!$pure_id ?
            ", lib.name as libname, lib.id as lib_id, lib.dtmpl_id":"") .
            " from csct_lib_content lc, csct_lib_content_names lcn" . (!$pure_id ? ", csct_library lib":"") .
            " where lc.id=lcn.data_id" . (!$pure_id ? " and lc.ref_id=lib.id":"");
        if ($lib_str)
            $query .= " and lc.ref_id in (" . $lib_str . ")";
        if ($inid)
            $query .= " and lc.id in (" . $inid . ")";
        if ($qss)
            $query .= " and lcn.name like '%" . $qss . "%'";
        if ($check_status !== false)
            $query .= " and lc.status=" . $check_status;
        $query .= " group by lc.id order by lc.num asc";
        $query .= " limit " . $start . ", " . $limit;
        $list = $this->dbh->queryFetchAll($query);
        if ($pp) {
            $data_id = 0;
            $lid = 0;
            $query = "select * from csct_lib_content_names where lang_id=:lid and data_id=:data_id";
            $sql = $this->dbh->prepare($query);
            $sql->bindParam(':lid', $lid, PDO::PARAM_INT);
            $sql->bindParam(':data_id', $data_id, PDO::PARAM_INT);
            if (app()->ml) {
                foreach ($list as $key => $item) {
                    $data_id = $item['id'];
                    foreach (app()->csct_langs as $lid => $lang_name) {
                        $sql->execute();
                        $list[$key]['text'][$lid] = $sql->fetch();
                        $sql->closeCursor();
                    }
                }
            }
            else {
                $lid = 0;
                foreach ($list as $key => $item) {
                    $data_id = $item['id'];
                    $sql->execute();
                    $list[$key]['text'][$lid] = $sql->fetch();
                    $sql->closeCursor();
                }
            }
        }
        return array('qty' => $total_recs, 'list' => $list);
    }

    /**
     * model_cmain::dtmpl_process()
     * 
     * @return
     */
    function dtmpl_process()
    {
        $tdata_id = $_POST['tdata_id'];
        $templ_id = $_POST['dtmpl_id'];
        if ($_POST['data_type'] == 1)
            $query = "select * from csct_pages where id=" . $tdata_id;
        elseif ($_POST['data_type'] == 2)
            $query = "select * from csct_library where id=" . $tdata_id;
        elseif ($_POST['data_type'] == 3)
            $query = "select * from csct_pages where id=" . $_POST['parent_id'];
        elseif ($_POST['data_type'] == 4)
            $query = "select * from csct_page_groups where id=" . $tdata_id;
        $tdata = $this->dbh->query($query)->fetch();
        //проверка мультияза
        if (app()->ml) {
            if ($_POST['data_type'] == 2)
                $n_use_ml = app()->ml;
            else
                $n_use_ml = $_POST['use_ml'];
        }

        if (($_POST['data_type'] == 1 || $_POST['data_type'] == 3) && app()->ml)
            $use_ml = $tdata['use_ml'];
        else
            $use_ml = app()->ml;
        $sites_query = "delete from csct_ds_links where data_id=" . $tdata_id;
        $clearSitesSql = $this->dbh->prepare($sites_query);
        $siteId = 0;
        $sites_query = "insert into csct_ds_links (data_id, field_id, site_id) values ('" . $tdata_id .
            "', :field_id, :site_id)";
        $insertSitesSql = $this->dbh->prepare($sites_query);
        $insertSitesSql->bindParam(':field_id', $field_id, PDO::PARAM_INT);
        $insertSitesSql->bindParam(':site_id', $siteId, PDO::PARAM_INT);
        $field_query = "select * from csct_dtmpl_fields where dtmpl_id='" . $templ_id . "' order by num asc";

        $field_id = 0;
        $sel_field_query = "select * from csct_tdata_fields where data_id='" . $tdata_id .
            "' and field_id=:field_id";
        $sel_field_sql = $this->dbh->prepare($sel_field_query);
        $sel_field_sql->bindParam(':field_id', $field_id, PDO::PARAM_INT);

        foreach ($this->dbh->query($field_query) as $row) {
            $field_id = $row['id'];
            $field_name = "field_" . $tdata_id . "_" . $field_id;

            if ($row['ftype'] == 0) {
                if (!$row['multi']) {
                    if (app()->ml && $use_ml && $row['is_ml']) {
                        foreach (app()->csct_langs as $lid => $lang_name) {
                            $l_field_name = $field_name . "_lid_" . $lid;
                            $fvalue = $_POST[$l_field_name];
                            $this->upd_td_fields($tdata_id, $field_id, 0, $fvalue, $lid);
                        }
                    }
                    else {
                        $fvalue = $_POST[$field_name];
                        $this->upd_td_fields($tdata_id, $field_id, 0, $fvalue, 0);
                    }
                }
                else {
                    $sel_field_sql->execute();
                    $fvals = $sel_field_sql->fetchAll();
                    $sel_field_sql->closeCursor();
                    foreach ($fvals as $itm) {
                        if (app()->ml && $use_ml && $row['is_ml']) {
                            foreach (app()->csct_langs as $lid => $lang_name) {
                                $l_field_name = $field_name . "_lid_" . $lid . "_fld_" . $itm['id'];
                                if (isset($_POST[$l_field_name])) {
                                    $fvalue = $_POST[$l_field_name];
                                    $this->upd_td_fields($tdata_id, $field_id, 0, $fvalue, $lid, $itm['id']);
                                }
                            }
                        }
                        else {
                            if (isset($_POST[$field_name . "_fld_" . $itm['id']])) {
                                $fvalue = $_POST[$field_name . "_fld_" . $itm['id']];
                                $this->upd_td_fields($tdata_id, $field_id, 0, $fvalue, 0, $itm['id']);
                            }
                        }
                    }
                }
            }
            elseif ($row['ftype'] == 5) {
                $fvalue = $_POST[$field_name];
                $this->upd_td_fields($tdata_id, $field_id, $row['ftype'], $fvalue, 0);
            }
            elseif ($row['ftype'] == 3 || $row['ftype'] == 1) {
                if (!$row['multi']) {
                    if (isset($_POST[$field_name])) {
                        $fvalue = $_POST[$field_name];
                        $this->upd_td_fields($tdata_id, $field_id, $row['ftype'], $fvalue, 0);
                    }
                }
                else {
                    $sel_field_sql->execute();
                    $fvals = $sel_field_sql->fetchAll();
                    $sel_field_sql->closeCursor();
                    foreach ($fvals as $itm) {
                        if (isset($_POST[$field_name . "_fld_" . $itm['id']])) {
                            $fvalue = $_POST[$field_name . "_fld_" . $itm['id']];
                            $this->upd_td_fields($tdata_id, $field_id, $row['ftype'], $fvalue, 0, $itm['id']);
                        }
                    }
                }
            }
            elseif ($row['ftype'] == 4) {
                if (isset($_POST[$field_name])) {
                    $fvalue = $_POST[$field_name];
                    $this->upd_td_fields($tdata_id, $field_id, 4, $fvalue, 0);
                }
            }
            elseif ($row['ftype'] == 7) {

                if (!$row['multi']) {
                    if (isset($_POST[$field_name])) {
                        list($d, $m, $y) = explode(".", $_POST[$field_name]);
                        $fvalue = $y . '-' . $m . '-' . $d;
                        $this->upd_td_fields($tdata_id, $field_id, 7, $fvalue, 0);
                    }
                }
                else {
                    $sel_field_sql->execute();
                    $fvals = $sel_field_sql->fetchAll();
                    $sel_field_sql->closeCursor();
                    foreach ($fvals as $itm) {
                        if (isset($_POST[$field_name . "_fld_" . $itm['id']])) {
                            list($d, $m, $y) = explode(".", $_POST[$field_name . "_fld_" . $itm['id']]);
                            $fvalue = $y . '-' . $m . '-' . $d;
                            $this->upd_td_fields($tdata_id, $field_id, 7, $fvalue, 0, $itm['id']);
                        }
                    }
                }

            }
            elseif ($row['ftype'] == 8) {
                $this->dbh->exec("delete from csct_dp_links where field_id=" . $row['id'] . " and data_id=" . $tdata_id);
                if (isset($_POST[$field_name]) && $_POST[$field_name]) {
                    $items = explode(",", $_POST[$field_name]);
                    foreach ($items as $item) {
                        list($ltype, $item_id) = explode("_", $item);
                        $this->dbh->exec("insert into csct_dp_links values ('', '" . $_POST['tdata_id'] . "', '" . $field_id .
                            "', '" . $ltype . "', '" . $item_id . "')");
                    }
                }
            }
            elseif ($row['ftype'] == 9) {
                if (isset($_POST[$field_name]) && $_POST[$field_name])
                    $this->upd_td_fields($tdata_id, $field_id, 9, $_POST[$field_name], 0);
                else
                    $this->upd_td_fields($tdata_id, $field_id, 9, 0, 0);

            }
            elseif ($row['ftype'] == 10) {
                $clearSitesSql->execute();
                $clearSitesSql->closeCursor();
                if (isset($_POST[$field_name]) && $_POST[$field_name]) {
                    foreach ($_POST[$field_name] as $siteId) {
                        $insertSitesSql->execute();
                        $insertSitesSql->closeCursor();
                    }
                }
            }
        }
        if (($_POST['data_type'] == 1 || $_POST['data_type'] == 3) && app()->ml) {

            if ($use_ml != $n_use_ml) {
                $queries = array();
                //$queries[] = "update " . $base_table . " set use_ml=" . $n_use_ml . " where id=" . $tdata_id;
                $to_lang = $use_ml ? 0:app()->lang_main;
                $from_lang = $use_ml ? app()->lang_main:0;
                //$queries[] = "update " . ($_POST['data_type'] == 1 ? "csct_pages":"csct_library") . "_names set lang_id=" . $to_lang . " where lang_id=" . $from_lang .
                //    " and data_id=" . $tdata_id;
                $queries[] = "update csct_tdata_fields set lang_id='" . $to_lang . "' where lang_id='" . $from_lang .
                    "' and data_id=" . $tdata_id .
                    " and field_id in (select id from csct_dtmpl_fields where ftype=0 and is_ml=1 and dtmpl_id=(select dtmpl_id from " . ($_POST['data_type'] ==
                    1 ? "csct_pages":"csct_library") . " where id=" . $tdata_id . "))";
                foreach ($queries as $query)
                    $this->dbh->exec($query);
            }
        }
        //группировки
        $query = "select mt.*, ft.fields from csct_tdata_groups mt, csct_dtmpl_groups ft where mt.dtmpl_id=" .
            $templ_id . " and mt.data_id=" . $tdata_id . " and mt.group_id=ft.id";
        $groups = $this->dbh->queryFetchAll($query);
        if ($groups) {
            $field_id = 0;
            $sel_field_query = "select * from csct_tgdata_fields where data_id='" . $tdata_id .
                "' and field_id=:field_id and group_id=:group_id";
            $sel_field_sql = $this->dbh->prepare($sel_field_query);
            $sel_field_sql->bindParam(':field_id', $field_id, PDO::PARAM_INT);
            $sel_field_sql->bindParam(':group_id', $group_id, PDO::PARAM_INT);

            $sites_query = "delete from csct_dgs_links where group_id=:group_id and data_id=" . $tdata_id;
            $clearGSitesSql = $this->dbh->prepare($sites_query);
            $clearGSitesSql->bindParam(':group_id', $group_id, PDO::PARAM_INT);
            $siteId = 0;
            $sites_query = "insert into csct_dgs_links (data_id, field_id, group_id, site_id) values ('" . $tdata_id .
                "', :field_id, :group_id, :site_id)";
            $insertGSitesSql = $this->dbh->prepare($sites_query);
            $insertGSitesSql->bindParam(':field_id', $field_id, PDO::PARAM_INT);
            $insertGSitesSql->bindParam(':site_id', $siteId, PDO::PARAM_INT);
            $insertGSitesSql->bindParam(':group_id', $group_id, PDO::PARAM_INT);

            foreach ($groups as $group) {
                $field_query = "select * from csct_dtmpl_fields where id in (" . $group['fields'] .
                    ") order by num asc";
                foreach ($this->dbh->query($field_query) as $row) {
                    $field_id = $row['id'];
                    $group_id = $group['id'];
                    $field_name = "group_" . $group['id'] . "_field_" . $tdata_id . "_" . $field_id;

                    if ($row['ftype'] == 0) {
                        if (!$row['multi']) {
                            if (app()->ml && $use_ml && $row['is_ml']) {
                                foreach (app()->csct_langs as $lid => $lang_name) {
                                    $l_field_name = $field_name . "_lid_" . $lid;
                                    if (isset($_POST[$l_field_name])) {
                                        $fvalue = $_POST[$l_field_name];
                                        $this->upd_tgd_fields($group['id'], $tdata_id, $field_id, 0, $fvalue, $lid);
                                    }
                                }
                            }
                            else {
                                if (isset($_POST[$field_name])) {
                                    $fvalue = $_POST[$field_name];
                                    $this->upd_tgd_fields($group['id'], $tdata_id, $field_id, 0, $fvalue, 0);
                                }
                            }
                        }
                        else {
                            $sel_field_sql->execute();
                            $fvals = $sel_field_sql->fetchAll();
                            $sel_field_sql->closeCursor();
                            foreach ($fvals as $itm) {
                                if (app()->ml && $use_ml && $row['is_ml']) {
                                    foreach (app()->csct_langs as $lid => $lang_name) {
                                        $l_field_name = $field_name . "_lid_" . $lid . "_fld_" . $itm['id'];
                                        if (isset($_POST[$l_field_name])) {
                                            $fvalue = $_POST[$l_field_name];
                                            $this->upd_tgd_fields($group['id'], $tdata_id, $field_id, 0, $fvalue, $lid, $itm['id']);
                                        }
                                    }
                                }
                                else {
                                    if (isset($_POST[$field_name . "_fld_" . $itm['id']])) {
                                        $fvalue = $_POST[$field_name . "_fld_" . $itm['id']];
                                        $this->upd_tgd_fields($group['id'], $tdata_id, $field_id, 0, $fvalue, 0, $itm['id']);
                                    }
                                }
                            }
                        }
                    }
                    elseif ($row['ftype'] == 5) {
                        $fvalue = $_POST[$field_name];
                        $this->upd_tgd_fields($group['id'], $tdata_id, $field_id, $row['ftype'], $fvalue, 0);
                    }
                    elseif ($row['ftype'] == 10) {
                        $clearGSitesSql->execute();
                        $clearGSitesSql->closeCursor();
                        if (isset($_POST[$field_name]) && $_POST[$field_name]) {
                            foreach ($_POST[$field_name] as $siteId) {
                                $insertGSitesSql->execute();
                                $insertGSitesSql->closeCursor();
                            }
                        }
                    }
                    elseif ($row['ftype'] == 3 || $row['ftype'] == 1) {
                        if (!$row['multi']) {
                            $fvalue = $_POST[$field_name];
                            $this->upd_tgd_fields($group['id'], $tdata_id, $field_id, $row['ftype'], $fvalue, 0);
                        }
                        else {
                            $sel_field_sql->execute();
                            $fvals = $sel_field_sql->fetchAll();
                            $sel_field_sql->closeCursor();
                            foreach ($fvals as $itm) {
                                if (isset($_POST[$field_name . "_fld_" . $itm['id']])) {
                                    $fvalue = $_POST[$field_name . "_fld_" . $itm['id']];
                                    $this->upd_tgd_fields($group['id'], $tdata_id, $field_id, $row['ftype'], $fvalue, 0, $itm['id']);
                                }
                            }
                        }
                    }
                    elseif ($row['ftype'] == 4) {
                        $fvalue = $_POST[$field_name];
                        $this->upd_tgd_fields($group['id'], $tdata_id, $field_id, 4, $fvalue, 0);
                    }
                    elseif ($row['ftype'] == 7) {

                        if (!$row['multi']) {
                            list($d, $m, $y) = explode(".", $_POST[$field_name]);
                            $fvalue = $y . '-' . $m . '-' . $d;
                            $this->upd_tgd_fields($group['id'], $tdata_id, $field_id, 7, $fvalue, 0);
                        }
                        else {
                            $sel_field_sql->execute();
                            $fvals = $sel_field_sql->fetchAll();
                            $sel_field_sql->closeCursor();
                            foreach ($fvals as $itm) {
                                if (isset($_POST[$field_name . "_fld_" . $itm['id']])) {
                                    list($d, $m, $y) = explode(".", $_POST[$field_name . "_fld_" . $itm['id']]);
                                    $fvalue = $y . '-' . $m . '-' . $d;
                                    $this->upd_tgd_fields($group['id'], $tdata_id, $field_id, 7, $fvalue, 0, $itm['id']);
                                }
                            }
                        }

                    }
                    elseif ($row['ftype'] == 8) {
                        $this->dbh->exec("delete from csct_dgp_links where group_id=" . $group['id'] . " and field_id=" . $row['id'] .
                            " and data_id=" . $tdata_id);
                        if (isset($_POST[$field_name]) && $_POST[$field_name]) {
                            $items = explode(",", $_POST[$field_name]);
                            foreach ($items as $item) {
                                list($ltype, $item_id) = explode("_", $item);
                                $this->dbh->exec("insert into csct_dgp_links values ('', '" . $_POST['tdata_id'] . "', '" . $group['id'] .
                                    "', '" . $field_id . "', '" . $ltype . "', '" . $item_id . "')");
                            }
                        }
                    }
                    elseif ($row['ftype'] == 9) {
                        if (isset($_POST[$field_name]) && $_POST[$field_name])
                            $this->upd_tgd_fields($group['id'], $tdata_id, $field_id, 9, $_POST[$field_name], 0);
                        else
                            $this->upd_tgd_fields($group['id'], $tdata_id, $field_id, 9, 0, 0);

                    }
                }
            }
        }
    }

    /**
     * model_cmain::upd_td_fields()
     * 
     * @param mixed $tdata_id
     * @param mixed $field_id
     * @param mixed $field_type
     * @param mixed $field_value
     * @param integer $lang_id
     * @return
     */
    function upd_td_fields($tdata_id, $field_id, $field_type, $field_value, $lang_id = 0, $fv_id = null)
    {
        $query = "select count(*) from csct_tdata_fields where lang_id='" . $lang_id . "' and data_id='" . $tdata_id .
            "' and field_id='" . $field_id . "'";
        if ($fv_id)
            $query .= " and id=" . $fv_id;
        $count = current($this->dbh->query($query)->fetch());
        $query = "update csct_tdata_fields set fvalue=:fvalue, fnvalue=:fnvalue, fdvalue=:fdvalue where data_id=:tdata_id and field_id=:field_id and lang_id=:lang_id";
        if ($fv_id)
            $query .= " and id=" . $fv_id;
        $fvalue = '';
        $fnvalue = 0;
        $fdvalue = '';
        $fusql = $this->dbh->prepare($query);
        $fusql->bindParam(':fvalue', $fvalue, PDO::PARAM_STR);
        $fusql->bindParam(':fdvalue', $fdvalue, PDO::PARAM_STR);
        $fusql->bindParam(':fnvalue', $fnvalue, PDO::PARAM_INT);
        $fusql->bindParam(':tdata_id', $tdata_id, PDO::PARAM_INT);
        $fusql->bindParam(':field_id', $field_id, PDO::PARAM_INT);
        $fusql->bindParam(':lang_id', $lang_id, PDO::PARAM_INT);
        $query = "insert into csct_tdata_fields values ('', :tdata_id, :field_id, :lang_id, :fvalue, :fnvalue, :fdvalue)";
        $fisql = $this->dbh->prepare($query);
        $fisql->bindParam(':fvalue', $fvalue, PDO::PARAM_STR);
        $fisql->bindParam(':fdvalue', $fdvalue, PDO::PARAM_STR);
        $fisql->bindParam(':fnvalue', $fnvalue, PDO::PARAM_INT);
        $fisql->bindParam(':tdata_id', $tdata_id, PDO::PARAM_INT);
        $fisql->bindParam(':field_id', $field_id, PDO::PARAM_INT);
        $fisql->bindParam(':lang_id', $lang_id, PDO::PARAM_INT);
        if (in_array($field_type, array(0, 1)))
            $fvalue = $field_value;
        elseif ($field_type == 7)
            $fdvalue = $field_value;
        else
            $fnvalue = $field_value;
        if ($count) {
            $fusql->execute();
            $fusql->closeCursor();
        }
        else {
            $fisql->execute();
            $fisql->closeCursor();
        }
    }

    function upd_tgd_fields($group_id, $tdata_id, $field_id, $field_type, $field_value, $lang_id = 0, $fv_id = null)
    {
        $query = "select count(*) from csct_tgdata_fields where group_id='" . $group_id . "' and lang_id='" .
            $lang_id . "' and data_id='" . $tdata_id . "' and field_id='" . $field_id . "'";
        if ($fv_id)
            $query .= " and id=" . $fv_id;
        $count = current($this->dbh->query($query)->fetch());
        $query = "update csct_tgdata_fields set fvalue=:fvalue, fnvalue=:fnvalue, fdvalue=:fdvalue where data_id=:tdata_id and field_id=:field_id and lang_id=:lang_id and group_id=:group_id";
        if ($fv_id)
            $query .= " and id=" . $fv_id;
        $fvalue = '';
        $fnvalue = 0;
        $fdvalue = '';
        $fusql = $this->dbh->prepare($query);
        $fusql->bindParam(':fvalue', $fvalue, PDO::PARAM_STR);
        $fusql->bindParam(':fdvalue', $fdvalue, PDO::PARAM_STR);
        $fusql->bindParam(':fnvalue', $fnvalue, PDO::PARAM_INT);
        $fusql->bindParam(':tdata_id', $tdata_id, PDO::PARAM_INT);
        $fusql->bindParam(':group_id', $group_id, PDO::PARAM_INT);
        $fusql->bindParam(':field_id', $field_id, PDO::PARAM_INT);
        $fusql->bindParam(':lang_id', $lang_id, PDO::PARAM_INT);
        $query = "insert into csct_tgdata_fields values ('', :tdata_id, :group_id, :field_id, :lang_id, :fvalue, :fnvalue, :fdvalue)";
        $fisql = $this->dbh->prepare($query);
        $fisql->bindParam(':fvalue', $fvalue, PDO::PARAM_STR);
        $fisql->bindParam(':fdvalue', $fdvalue, PDO::PARAM_STR);
        $fisql->bindParam(':fnvalue', $fnvalue, PDO::PARAM_INT);
        $fisql->bindParam(':tdata_id', $tdata_id, PDO::PARAM_INT);
        $fisql->bindParam(':group_id', $group_id, PDO::PARAM_INT);
        $fisql->bindParam(':field_id', $field_id, PDO::PARAM_INT);
        $fisql->bindParam(':lang_id', $lang_id, PDO::PARAM_INT);
        if (in_array($field_type, array(0, 1)))
            $fvalue = $field_value;
        elseif ($field_type == 7)
            $fdvalue = $field_value;
        else
            $fnvalue = $field_value;
        if ($count) {
            $fusql->execute();
            $fusql->closeCursor();
        }
        else {
            $fisql->execute();
            $fisql->closeCursor();
        }
    }

    /**
     * model_cmain::set_order()
     * 
     * @param mixed $table
     * @return
     */
    function set_order($table)
    {
        $num = 0;
        $id = 0;
        $query = "update " . $table . " set num=:num where id=:id";
        $sql = $this->dbh->prepare($query);
        $sql->bindParam(':num', $num, PDO::PARAM_INT);
        $sql->bindParam(':id', $id, PDO::PARAM_INT);
        $ids = array();
        $nums = array();
        $list = !isset($_POST['qst_id']) ? 'qst':$_POST['qst_id'];
        foreach ($_POST[$list] as $row) {
            if ($row) {
                list($id, $num) = explode("_", $row);
                $ids[] = $id;
                $nums[] = $num;
            }
        }
        sort($nums);
        foreach ($ids as $key => $id) {
            $num = $nums[$key];
            $sql->execute();
            $sql->closeCursor();
        }
    }

    /**
     * model_cmain::page_process()
     * 
     * @param mixed $page_data
     * @return
     */
    function page_process($page_data)
    {
        $this->trigger_execute('beforePageEdit', $_POST['tdata_id']);
        if (isset($_POST['address'])) {
            if ((!$_POST['address'] && isset($_POST['generate_address']) && $_POST['generate_address']) ||
                preg_match('/^copy_/i', $_POST['address'])) {
                $hdr = $page_data['use_ml'] && app()->ml ? $_POST['header_' . app()->lang_main]:$_POST['header'];
                $address = strtolower($this->trlit($hdr));
            }
            else
                $address = strpos($_POST['address'], 'http://') === false ? strtolower($this->trlit($_POST['address'])):
                    $_POST['address'];
        }
        if (isset($_POST['file_link']))
            $file_link = $_POST['file_link'];
        $pl_fields = array('address', 'file_link');
        $po_fields = array(
            'template' => 'template',
            'subtemplate' => 'stmpl',
            'dtmpl_id' => 'dtmpl_id',
            'lib_id' => 'lib_id',
            'db_type' => 'db_type',
            'archive' => 'archive',
            'service' => 'service',
            'p_access' => 'p_access',
            'show_menu' => 'show_menu',
            'ltype' => 'ltype',
            'page_snp' => 'page_snp',
            'use_photo' => 'use_photo',
            'use_comments' => 'use_comments',
            'moderation' => 'moderation',
            'phg_width' => 'phg_width',
            'phg_height' => 'phg_height',
            'phg_mwidth' => 'phg_mwidth',
            'phg_mheight' => 'phg_mheight',
            'phg_wm' => 'phg_wm',
            'parent' => 'parent');
        $po_fields_2 = array('kw_type', 'mdescr_type');
        $query = "update csct_pages set edtime=NOW(), ed_user_id=" . $this->registry->user_id;
        foreach ($pl_fields as $pl_field)
            if (isset($_POST[$pl_field]))
                $query .= ", " . $pl_field . "=:" . $pl_field;
        foreach ($po_fields as $key => $po_field)
            if (isset($_POST[$po_field]))
                $query .= ", " . $key . "='" . $_POST[$po_field] . "'";
        if (isset($_POST['plink']))
            $query .= ", plink=" . ($_POST['plink'] ? $_POST['plink']:0);
        /*", address=:address, template=" . $_POST['template'] . ", subtemplate=" . $_POST['stmpl'] .
        ", dtmpl_id=" . $_POST['dtmpl_id'] . ", db_type=" . $_POST['db_type'] . ", plink=" . ($_POST['plink'] ?
        $_POST['plink']:0) . ", archive=" . $_POST['archive'] . ", show_menu=" . $_POST['show_menu'] .
        ", ltype=" . $_POST['ltype'] . ", page_snp=" . $_POST['page_snp'] . ", use_photo=" . $_POST['use_photo'] .
        ", photo_snp=" . $_POST['photo_snp'] . ", file_link=:file_link";*/
        if ($page_data['db_type'] != 2) {
            //$query .= ", kw_type=" . $_POST['kw_type'] . ", mdescr_type=" . $_POST['mdescr_type'];
            foreach ($po_fields_2 as $po_field)
                if (isset($_POST[$po_field]))
                    $query .= ", " . $po_field . "='" . $_POST[$po_field] . "'";
        }

        //if ($_POST['parent']) {
        //list($pt, $parent) = explode("_", $_POST['parent']);
        //$ptype = $pt == 'page' ? 0:1;
        //$query .= ", parent=" . $_POST['parent'];
        //}
        if (app()->ml && isset($_POST['use_ml']))
            $query .= ", use_ml=" . $_POST['use_ml'];
        if (app()->md && isset($_POST['use_md'])) {
            $query .= ", use_md=" . $_POST['use_md'];

            if (app()->md && isset($_POST['use_md']) && $_POST['use_md'] && isset($_POST['site_id'])) {
                $squery = $this->get_sites($_POST['tdata_id']);
                $site_id = 0;
                $sq = "select id from csct_site_links where data_type=0 and data_id=" . $_POST['tdata_id'] .
                    " and site_id=:site_id";
                $ssql = $this->dbh->prepare($sq);
                $ssql->bindParam(':site_id', $site_id, PDO::PARAM_INT);
                foreach ($squery['sites'] as $site) {
                    if (in_array($site['id'], $_POST['site_id'])) {
                        $site_id = $site['id'];
                        $ssql->execute();
                        $rsd = $ssql->fetch();
                        $ssql->closeCursor();
                        if (!$rsd)
                            $this->dbh->exec("insert into csct_site_links (data_id, data_type, site_id) values ('" . $_POST['tdata_id'] .
                                "', '0', '" . $site_id . "')");
                    }
                    else
                        $this->dbh->exec("delete from csct_site_links where data_type=0 and data_id=" . $_POST['tdata_id'] .
                            " and site_id=" . $site['id']);
                }
            }
            else
                $this->dbh->exec("delete from csct_site_links where data_type=0 and data_id=" . $_POST['tdata_id']);
        }

        if ($page_data['db_type'] == 1) {
            $po_fields_3 = array(
                'use_preview',
                'sorting',
                'sort_reverse',
                'snp_list',
                'snp_list_item',
                'dtmpl_id_lc');
            foreach ($po_fields_3 as $po_field)
                if (isset($_POST[$po_field]))
                    $query .= ", " . $po_field . "='" . $_POST[$po_field] . "'";
        }
        /*
        $query .= ", use_preview=" . $_POST['use_preview'] . ", sorting=" . $_POST['sorting'] .
        ", sort_reverse=" . $_POST['sort_reverse'] . "
        , snp_list=" . $_POST['snp_list'] . ", snp_list_item=" . $_POST['snp_list_item'] .
        ", dtmpl_id_lc=" . $_POST['dtmpl_id_lc'];
        */

        if (isset($_POST['main_page']) && $_POST['main_page']) {
            $mpqry = 'update csct_pages set main_page=0';
            if (app()->md && isset($_POST['use_md']) && $_POST['use_md'] && isset($_POST['site_id']))
                $mpqry = " where id in (select data_id from csct_site_links where data_type=0 and site_id in " . (join
                    (",", $_POST['site_id'])) . ")";
            $this->dbh->exec($mpqry);
            $query .= ", main_page=1";
        }

        if (isset($_POST['new_page']))
            $query .= ", status=1";
        $query .= " where id=" . $_POST['tdata_id'];
        $sql = $this->dbh->prepare($query);
        foreach ($pl_fields as $pl_field)
            if (isset($$pl_field))
                $sql->bindParam(':' . $pl_field, $$pl_field, PDO::PARAM_STR);

        $sql->execute();
        $sql->closeCursor();
        if ($page_data['use_photo']) {
            $pic_list = $this->get_piclist($_POST['tdata_id'], 1, true);
            if ($pic_list && app()->check_right('r_photo'))
                $this->piclist_process($pic_list, $page_data['use_ml']);
        }
        if ($this->registry['user_settings']['acl'] == 2) {
            $this->dbh->exec('delete from csct_userlinks where data_type=0 and data_id=' . $_POST['tdata_id']);
            if ($_POST['ousers'])
                foreach ($_POST['ousers'] as $user)
                    $this->dbh->exec("insert into csct_userlinks values ('', '" . $_POST['tdata_id'] . "', '0', '" . $user .
                        "')");
        }
        /*
        //привязки
        $this->dbh->exec('delete from csct_parent where data_id=' . $_POST['tdata_id']);
        if (isset($_POST['parent']) && $_POST['parent']) {
        $parent_id = 0;
        $query = "insert into csct_parent values ('', '" . $_POST['tdata_id'] . "', :parent_id)";
        $par_sql = $this->dbh->prepare($query);
        $par_sql->bindParam(':parent_id', $parent_id, PDO::PARAM_INT);
        foreach ($_POST['parent'] as $parent_id) {
        $par_sql->execute();
        $par_sql->closeCursor();
        }
        }*/
        //группы
        //$query = "delete from csct_pg_link where data_id=" . $_POST['tdata_id'];
        //$this->dbh->exec($query);
        if (app()->check_right('r_params')) {

            $pg_id = 0;
            $num = 1;
            $query = "insert into csct_pg_link values ('', '" . $_POST['tdata_id'] . "', :pg_id, :num)";
            $pg_sql = $this->dbh->prepare($query);
            $pg_sql->bindParam(':pg_id', $pg_id, PDO::PARAM_INT);
            $pg_sql->bindParam(':num', $num, PDO::PARAM_INT);
            //$query = "select id from csct_pg_link where data_id=" . $_POST['tdata_id'] . " and pg_id=:pg_id";
            //$pg_isql = $this->dbh->prepare($query);
            //$pg_isql->bindParam(':pg_id', $pg_id, PDO::PARAM_INT);
            $query = "delete from csct_pg_link where data_id=" . $_POST['tdata_id'] . " and pg_id=:pg_id";
            $pg_dsql = $this->dbh->prepare($query);
            $pg_dsql->bindParam(':pg_id', $pg_id, PDO::PARAM_INT);
            $query = "select max(num) from csct_pg_link where pg_id=:pg_id";
            $pg_nsql = $this->dbh->prepare($query);
            $pg_nsql->bindParam(':pg_id', $pg_id, PDO::PARAM_INT);
            $query = "select pg_id from csct_pg_link where data_id=" . $_POST['tdata_id'];
            $exist_pgl = $this->dbh->query($query)->fetchAll(PDO::FETCH_COLUMN);
            if ($exist_pgl) {
                foreach ($exist_pgl as $key => $val) {
                    if (isset($_POST['page_group']) && $_POST['page_group']) {
                        foreach ($_POST['page_group'] as $nkey => $nval) {
                            if ($val == $nval) {
                                unset($_POST['page_group'][$nkey]);
                                unset($exist_pgl[$key]);
                                break;
                            }
                        }
                    }
                }
            }

            //новые
            if (isset($_POST['page_group']) && $_POST['page_group']) {
                foreach ($_POST['page_group'] as $pg_id) {
                    $pg_nsql->execute();
                    $nr = $pg_nsql->fetch();
                    $num = $nr ? current($pg_nsql) + 1:1;
                    $pg_nsql->closeCursor();
                    $pg_sql->execute();
                    $pg_sql->closeCursor();
                }
            }
            //удаляем старые
            if ($exist_pgl) {
                foreach ($exist_pgl as $pg_id) {
                    $pg_dsql->execute();
                    $pg_dsql->closeCursor();
                }
            }
        }

        //текст
        if (app()->check_right('r_text') || app()->check_right('r_seo')) {
            $lid = 0;
            $header = '';
            $subheader = '';
            $title = '';
            $page_text = '';
            $menu_name = '';
            $seo_kw = '';
            $seo_descr = '';

            $query = "update csct_pages_text set header=:header, subheader=:subheader, title=:title, page_text=:page_text, menu_name=:menu_name, seo_kw=:seo_kw, seo_descr=:seo_descr where data_id=" .
                $_POST['tdata_id'] . " and lang_id=:lid";
            $upd_sql = $this->dbh->prepare($query);
            $upd_sql->bindParam(':lid', $lid, PDO::PARAM_INT);
            $upd_sql->bindParam(':header', $header, PDO::PARAM_STR);
            $upd_sql->bindParam(':subheader', $subheader, PDO::PARAM_STR);
            $upd_sql->bindParam(':title', $title, PDO::PARAM_STR);
            $upd_sql->bindParam(':page_text', $page_text, PDO::PARAM_STR);
            $upd_sql->bindParam(':menu_name', $menu_name, PDO::PARAM_STR);
            $upd_sql->bindParam(':seo_kw', $seo_kw, PDO::PARAM_STR);
            $upd_sql->bindParam(':seo_descr', $seo_descr, PDO::PARAM_STR);
            $query = "insert into csct_pages_text values ('', '" . $_POST['tdata_id'] .
                "', :lid, :header, :subheader, :title, :page_text, :menu_name, :seo_kw, :seo_descr)";
            $ins_sql = $this->dbh->prepare($query);
            $ins_sql->bindParam(':lid', $lid, PDO::PARAM_INT);
            $ins_sql->bindParam(':header', $header, PDO::PARAM_STR);
            $ins_sql->bindParam(':subheader', $subheader, PDO::PARAM_STR);
            $ins_sql->bindParam(':title', $title, PDO::PARAM_STR);
            $ins_sql->bindParam(':page_text', $page_text, PDO::PARAM_STR);
            $ins_sql->bindParam(':menu_name', $menu_name, PDO::PARAM_STR);
            $ins_sql->bindParam(':seo_kw', $seo_kw, PDO::PARAM_STR);
            $ins_sql->bindParam(':seo_descr', $seo_descr, PDO::PARAM_STR);
            $query = "select * from csct_pages_text where data_id=" . $_POST['tdata_id'] . " and lang_id=:lid";
            $sel_sql = $this->dbh->prepare($query);
            $sel_sql->bindParam(':lid', $lid, PDO::PARAM_INT);

            if ($page_data['use_ml'] && app()->ml) {
                foreach (app()->csct_langs as $lid => $lang_name) {
                    $sel_sql->execute();
                    $text_data = $sel_sql->fetch();
                    $sel_sql->closeCursor();
                    $header = isset($_POST['header_' . $lid]) ? $_POST['header_' . $lid]:$text_data['header'];
                    $subheader = isset($_POST['subheader_' . $lid]) ? $_POST['subheader_' . $lid]:$text_data['subheader'];
                    $title = isset($_POST['title_' . $lid]) ? $_POST['title_' . $lid]:$text_data['title'];
                    //$page_text = $_POST['stmpl'] == 0 ? (isset($_POST['page_text_' . $lid]) ? $_POST['page_text_' . $lid]:
                    //    ""):"";
                    $page_text = isset($_POST['page_text_' . $lid]) ? $_POST['page_text_' . $lid]:$text_data['page_text'];
                    $page_text = preg_replace('/<p>#{(.*)}#<\/p>/i', '#{\1}#', $page_text);
                    $page_text = preg_replace_callback('|#\{[^#]+\}#|U', array(&$this, "clear_tag"), $page_text);
                    $page_text = preg_replace_callback('|\[\*[^\*]+\*\]|U', array(&$this, "clear_tag"), $page_text);
                    $menu_name = isset($_POST['menu_name_' . $lid]) ? $_POST['menu_name_' . $lid]:$text_data['menu_name'];
                    if ($text_data) {
                        //$sel_sql->execute();
                        //$text_data = $sel_sql->fetch();
                        //$sel_sql->closeCursor();
                        $seo_descr = isset($_POST['seo_descr_' . $lid]) ? $_POST['seo_descr_' . $lid]:$text_data['seo_descr'];
                        $seo_kw = isset($_POST['seo_kw_' . $lid]) ? $_POST['seo_kw_' . $lid]:$text_data['seo_kw'];
                        $upd_sql->execute();
                        $upd_sql->closeCursor();
                    }
                    else {
                        $seo_descr = isset($_POST['seo_descr_' . $lid]) ? $_POST['seo_descr_' . $lid]:'';
                        $seo_kw = isset($_POST['seo_kw_' . $lid]) ? $_POST['seo_kw_' . $lid]:'';
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
                $header = isset($_POST['header']) ? $_POST['header']:$text_data['header'];
                $subheader = isset($_POST['subheader']) ? $_POST['subheader']:$text_data['subheader'];
                $title = isset($_POST['title']) ? $_POST['title']:$text_data['title'];
                //$page_text = $_POST['stmpl'] == 0 ? (isset($_POST['page_text']) ? $_POST['page_text']:""):"";
                $page_text = isset($_POST['page_text']) ? $_POST['page_text']:$text_data['page_text'];
                $page_text = preg_replace('/<p>#{(.*)}#<\/p>/i', '#{\1}#', $page_text);
                $page_text = preg_replace_callback('|#\{[^#]+\}#|U', array(&$this, "clear_tag"), $page_text);
                $page_text = preg_replace_callback('|\[\*[^\*]+\*\]|U', array(&$this, "clear_tag"), $page_text);
                $menu_name = $_POST['menu_name'];
                if ($text_data) {
                    //$sel_sql->execute();
                    //$text_data = $sel_sql->fetch();
                    //$sel_sql->closeCursor();
                    $seo_descr = isset($_POST['seo_descr']) ? $_POST['seo_descr']:$text_data['seo_descr'];
                    $seo_kw = isset($_POST['seo_kw']) ? $_POST['seo_kw']:$text_data['seo_kw'];
                    $upd_sql->execute();
                    $upd_sql->closeCursor();
                }
                else {
                    $seo_descr = isset($_POST['seo_descr']) ? $_POST['seo_descr']:'';
                    $seo_kw = isset($_POST['seo_kw']) ? $_POST['seo_kw']:'';
                    $ins_sql->execute();
                    $ins_sql->closeCursor();
                }
            }

            if (app()->ml && isset($_POST['use_ml'])) {
                //$use_ml = isset($_POST['use_ml']) ? 1:0;
                if ($page_data['use_ml'] != $_POST['use_ml']) {
                    $queries = array();
                    if ($_POST['use_ml']) {
                        $queries[] = "update csct_pages_text set lang_id=" . app()->lang_main . " where data_id=" . $_POST['tdata_id'];
                        $queries[] = "update csct_stmpl_data_text set lang_id=" . app()->lang_main .
                            " where page_type=0 and page_id=" . $_POST['tdata_id'];
                        $queries[] = "update csct_tdata_fields set lang_id=" . app()->lang_main . " where data_id=" . $_POST['tdata_id'] .
                            " and field_id in (select id from csct_dtmpl_fields where dtmpl_id=" . $_POST['dtmpl_id'] . ")";
                        $queries[] = "update csct_tgdata_fields set lang_id=" . app()->lang_main . " where data_id=" . $_POST['tdata_id'] .
                            " and field_id in (select id from csct_dtmpl_fields where dtmpl_id=" . $_POST['dtmpl_id'] . ")";
                        if ($page_data['db_type'] == 1) {
                            $queries[] = "update csct_list_items_text set lang_id=" . app()->lang_main .
                                " where data_id in (select id from csct_list_items where parent_id=" . $_POST['tdata_id'] . ")";
                            $queries[] = "update csct_stmpl_data_text set lang_id=" . app()->lang_main .
                                " where page_type=1 and page_id in (select id from csct_list_items where parent_id=" . $_POST['tdata_id'] .
                                ")";
                            $queries[] = "update csct_tdata_fields set lang_id=" . app()->lang_main .
                                " where data_id in (select id from csct_list_items where parent_id=" . $_POST['tdata_id'] .
                                ") and field_id in (select id from csct_dtmpl_fields where dtmpl_id=" . $_POST['dtmpl_id_lc'] . ")";
                            $queries[] = "update csct_tgdata_fields set lang_id=" . app()->lang_main .
                                " where data_id in (select id from csct_list_items where parent_id=" . $_POST['tdata_id'] .
                                ") and field_id in (select id from csct_dtmpl_fields where dtmpl_id=" . $_POST['dtmpl_id_lc'] . ")";
                        }
                    }
                    else {
                        $queries[] = "update csct_pages_text set lang_id=0 where data_id=" . $_POST['tdata_id'] .
                            " and lang_id=" . app()->lang_main;
                        $queries[] = "update csct_stmpl_data_text set lang_id=0 where page_type=0 and page_id=" . $_POST['tdata_id'] .
                            " and lang_id=" . app()->lang_main;
                        $queries[] = "update csct_tdata_fields set lang_id=0 where data_id=" . $_POST['tdata_id'] .
                            " and field_id in (select id from csct_dtmpl_fields where dtmpl_id=" . $_POST['dtmpl_id'] . ")" .
                            " and lang_id=" . app()->lang_main;
                        $queries[] = "update csct_tgdata_fields set lang_id=0 where data_id=" . $_POST['tdata_id'] .
                            " and field_id in (select id from csct_dtmpl_fields where dtmpl_id=" . $_POST['dtmpl_id'] . ")" .
                            " and lang_id=" . app()->lang_main;
                        if ($page_data['db_type'] == 1) {
                            $queries[] = "update csct_list_items_text set lang_id=0 where lang_id=" . app()->lang_main .
                                " and data_id in (select id from csct_list_items where parent_id=" . $_POST['tdata_id'] . ")";
                            $queries[] = "update csct_stmpl_data_text set lang_id=0 where page_type=1 and page_id in in (select id from csct_list_items where parent_id=" .
                                $_POST['tdata_id'] . ") and lang_id=" . app()->lang_main;

                            $queries[] = "update csct_tdata_fields set lang_id=0 where data_id in (select id from csct_list_items where parent_id=" .
                                $_POST['tdata_id'] . ") and field_id in (select id from csct_dtmpl_fields where dtmpl_id=" . $_POST['dtmpl_id_lc'] .
                                ") and lang_id=" . app()->lang_main;
                            $queries[] = "update csct_tgdata_fields set lang_id=0 where data_id in (select id from csct_list_items where parent_id=" .
                                $_POST['tdata_id'] . ") and field_id in (select id from csct_dtmpl_fields where dtmpl_id=" . $_POST['dtmpl_id_lc'] .
                                ") and lang_id=" . app()->lang_main;
                        }
                    }
                    foreach ($queries as $qry)
                        $this->dbh->exec($qry);
                }
            }
        }
        $this->trigger_execute('afterPageEdit', $_POST['tdata_id']);
    }

    /**
     * model_cmain::clear_tag()
     * 
     * @param mixed $cname
     * @return
     */
    function clear_tag($cname)
    {
        return $this->html2text($cname[0]);
    }

    /**
     * model_cmain::html2text()
     * 
     * @param mixed $string
     * @return
     */
    function html2text($string)
    {
        $search = array(
            "'<script[^>]*?>.*?</script>'si", // Вырезает javaScript
            "'<[\/\!]*?[^<>]*?>'si", // Вырезает HTML-теги
            "'([\r\n])[\s]+'", // Вырезает пробельные символы
            "'&(quot|#34);'i", // Заменяет HTML-сущности
            "'&(amp|#38);'i",
            "'&(amp|#39);'i",
            "'&(lt|#60);'i",
            "'&(gt|#62);'i",
            "'&(nbsp|#160);'i",
            "'&(iexcl|#161);'i",
            "'&(cent|#162);'i",
            "'&(pound|#163);'i",
            "'&(copy|#169);'i",
            "'&#(\d+);'e"); // интерпретировать как php-код

        $replace = array(
            "",
            "",
            "\\1",
            "\"",
            "&",
            "'",
            "<",
            ">",
            " ",
            chr(161),
            chr(162),
            chr(163),
            chr(169),
            "chr(\\1)");

        return preg_replace($search, $replace, $string);
    }

    /**
     * model_cmain::li_process()
     * 
     * @param mixed $head_page_data
     * @param mixed $page_data
     * @return
     */
    function li_process($head_page_data, $page_data)
    {
        $this->trigger_execute('beforeListItemEdit', $_POST['tdata_id']);
        if (isset($_POST['address'])) {
            if ((!$_POST['address'] && isset($_POST['lgenerate_address']) && $_POST['lgenerate_address']) ||
                preg_match('/^copy_/i', $_POST['address'])) {
                $hdr = $head_page_data['use_ml'] && app()->ml ? $_POST['lheader_' . app()->lang_main]:$_POST['lheader'];
                $address = strtolower($this->trlit($hdr));
            }
            else
                $address = strpos($_POST['address'], 'http://') === false ? strtolower($this->trlit($_POST['address'])):
                    $_POST['address'];
        }
        else
            $address = $page_data['address'];
        if (isset($_POST['dop']))
            list($d, $m, $y) = explode(".", $_POST['dop']);
        else
            list($d, $m, $y) = explode(".", $page_data['fdateofpub']);
        $query = "update csct_list_items set edtime=NOW(), ed_user_id=" . $this->registry->user_id .
            ", address=:address, dateofpub='" . $y . "-" . $m . "-" . $d . "', priority=" . (isset($_POST['priority']) ?
            $_POST['priority']:$page_data['priority']) . ", archive=" . (isset($_POST['larchive']) ? $_POST['larchive']:
            $page_data['archive']) . ", template=" . (isset($_POST['template']) ? $_POST['template']:$page_data['template']) .
            ", subtemplate=" . (isset($_POST['lstmpl']) ? $_POST['lstmpl']:$page_data['subtemplate']) .
            ", db_type=" . (isset($_POST['ldb_type']) ? $_POST['ldb_type']:$page_data['db_type']) . ", plink=" . (isset
            ($_POST['lplink']) ? ($_POST['lplink'] ? $_POST['lplink']:0):$page_data['plink']) . ", ltype=" . (isset
            ($_POST['lltype']) ? $_POST['lltype']:$page_data['ltype']) . ", use_photo=" . (isset($_POST['luse_photo']) ?
            $_POST['luse_photo']:$page_data['use_photo']) . ", use_comments=" . (isset($_POST['use_comments']) ?
            $_POST['use_comments']:$page_data['use_comments']) . ", moderation=" . (isset($_POST['moderation']) ?
            $_POST['moderation']:$page_data['moderation']) . ", photo_snp='" . (isset($_POST['lphoto_snp']) ? $_POST['lphoto_snp']:
            $page_data['photo_snp']) . "', phg_width='" . (isset($_POST['lphg_width']) ? $_POST['lphg_width']:$page_data['phg_width']) .
            "', phg_height='" . (isset($_POST['lphg_height']) ? $_POST['lphg_height']:$page_data['phg_height']) .
            "', phg_mwidth='" . (isset($_POST['lphg_mwidth']) ? $_POST['lphg_mwidth']:$page_data['phg_mwidth']) .
            "', phg_mheight='" . (isset($_POST['lphg_mheight']) ? $_POST['lphg_mheight']:$page_data['phg_mheight']) .
            "',phg_wm='" . (isset($_POST['lphg_wm']) ? $_POST['lphg_wm']:$page_data['lphg_wm']) .
            "', file_link=:file_link";
        if ($page_data['db_type'] != 1)
            $query .= ", kw_type=" . (isset($_POST['lkw_type']) ? $_POST['lkw_type']:$page_data['kw_type']) .
                ", mdescr_type=" . (isset($_POST['lmdescr_type']) ? $_POST['lmdescr_type']:$page_data['mdescr_type']);
        /*
        if (!$address)
        $query .= ", status=0";
        elseif ($address && isset($_POST['new_page']))
        $query .= ", status=1";
        */
        $query .= " where id=" . $_POST['tdata_id'];
        $sql = $this->dbh->prepare($query);
        $file_link = isset($_POST['lfile_link']) ? $_POST['lfile_link']:$page_data['file_link'];
        $sql->bindParam(':address', $address, PDO::PARAM_STR);
        $sql->bindParam(':file_link', $file_link, PDO::PARAM_STR);
        $sql->execute();
        $sql->closeCursor();

        if (app()->check_right('r_text_li') || app()->check_right('r_seo_li')) {
            $lid = 0;
            $header = '';
            $title = '';
            $page_text = '';
            $page_preview = '';
            $seo_kw = '';
            $seo_descr = '';
            $query = "select count(id) from csct_list_items_text where data_id=" . $_POST['tdata_id'] .
                " and lang_id=:lid";
            $isset_sql = $this->dbh->prepare($query);
            $isset_sql->bindParam(':lid', $lid, PDO::PARAM_INT);
            $query = "update csct_list_items_text set header=:header, title=:title, page_text=:page_text, page_preview=:page_preview, seo_kw=:seo_kw, seo_descr=:seo_descr where data_id=" .
                $_POST['tdata_id'] . " and lang_id=:lid";
            $upd_sql = $this->dbh->prepare($query);
            $upd_sql->bindParam(':lid', $lid, PDO::PARAM_INT);
            $upd_sql->bindParam(':header', $header, PDO::PARAM_STR);
            $upd_sql->bindParam(':title', $title, PDO::PARAM_STR);
            $upd_sql->bindParam(':page_text', $page_text, PDO::PARAM_STR);
            $upd_sql->bindParam(':page_preview', $page_preview, PDO::PARAM_STR);
            $upd_sql->bindParam(':seo_kw', $seo_kw, PDO::PARAM_STR);
            $upd_sql->bindParam(':seo_descr', $seo_descr, PDO::PARAM_STR);
            $query = "insert into csct_list_items_text values ('', '" . $_POST['tdata_id'] .
                "', :lid, :header, :title, :page_preview, :page_text, :seo_kw, :seo_descr)";
            $ins_sql = $this->dbh->prepare($query);
            $ins_sql->bindParam(':lid', $lid, PDO::PARAM_INT);
            $ins_sql->bindParam(':header', $header, PDO::PARAM_STR);
            $ins_sql->bindParam(':title', $title, PDO::PARAM_STR);
            $ins_sql->bindParam(':page_text', $page_text, PDO::PARAM_STR);
            $ins_sql->bindParam(':page_preview', $page_preview, PDO::PARAM_STR);
            $ins_sql->bindParam(':seo_kw', $seo_kw, PDO::PARAM_STR);
            $ins_sql->bindParam(':seo_descr', $seo_descr, PDO::PARAM_STR);

            if ($head_page_data['use_ml'] && app()->ml) {
                foreach (app()->csct_langs as $lid => $lang_name) {
                    $isset_sql->execute();
                    $isset_lid = current($isset_sql->fetch());
                    $isset_sql->closeCursor();
                    $header = isset($_POST['lheader_' . $lid]) ? $_POST['lheader_' . $lid]:$page_data['text'][$lid]['header'];
                    $title = isset($_POST['ltitle_' . $lid]) ? $_POST['ltitle_' . $lid]:$page_data['text'][$lid]['title'];
                    $seo_kw = isset($_POST['lseo_kw' . $lid]) ? $_POST['lseo_kw' . $lid]:$page_data['text'][$lid]['seo_kw'];
                    $seo_descr = isset($_POST['lseo_descr' . $lid]) ? $_POST['lseo_descr' . $lid]:$page_data['text'][$lid]['seo_descr'];
                    $page_text = isset($_POST['lpage_text_' . $lid]) ? $_POST['lpage_text_' . $lid]:$page_data['text'][$lid]['page_text'];
                    $page_text = preg_replace('/<p>#{(.*)}#<\/p>/i', '#{\1}#', $page_text);
                    $page_text = preg_replace_callback('|#\{[^#]+\}#|U', array(&$this, "clear_tag"), $page_text);
                    $page_text = preg_replace_callback('|\[\*[^\*]+\*\]|U', array(&$this, "clear_tag"), $page_text);
                    $page_preview = isset($_POST['lpage_preview_' . $lid]) ? $_POST['lpage_preview_' . $lid]:$page_data['text'][$lid]['page_preview'];
                    $page_preview = preg_replace('/<p>#{(.*)}#<\/p>/i', '#{\1}#', $page_preview);
                    $page_preview = preg_replace_callback('|#\{[^#]+\}#|U', array(&$this, "clear_tag"), $page_preview);
                    $page_preview = preg_replace_callback('|\[\*[^\*]+\*\]|U', array(&$this, "clear_tag"), $page_preview);

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
                $header = isset($_POST['lheader']) ? $_POST['lheader']:$page_data['text'][0]['header'];
                $title = isset($_POST['ltitle']) ? $_POST['ltitle']:$page_data['text'][0]['title'];
                $seo_kw = isset($_POST['lseo_kw']) ? $_POST['lseo_kw']:$page_data['text'][0]['seo_kw'];
                $seo_descr = isset($_POST['lseo_descr']) ? $_POST['lseo_descr']:$page_data['text'][0]['seo_descr'];
                $page_text = isset($_POST['lpage_text']) ? $_POST['lpage_text']:$page_data['text'][0]['page_text'];
                $page_text = preg_replace('/<p>#{(.*)}#<\/p>/i', '#{\1}#', $page_text);
                $page_text = preg_replace_callback('|#\{[^#]+\}#|U', array(&$this, "clear_tag"), $page_text);
                $page_text = preg_replace_callback('|\[\*[^\*]+\*\]|U', array(&$this, "clear_tag"), $page_text);
                $page_preview = isset($_POST['lpage_preview']) ? $_POST['lpage_preview']:$page_data['text'][0]['page_preview'];
                $page_preview = preg_replace('/<p>#{(.*)}#<\/p>/i', '#{\1}#', $page_preview);
                $page_preview = preg_replace_callback('|#\{[^#]+\}#|U', array(&$this, "clear_tag"), $page_preview);
                $page_preview = preg_replace_callback('|\[\*[^\*]+\*\]|U', array(&$this, "clear_tag"), $page_preview);
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
        if ($page_data['use_photo']) {
            $pic_list = $this->get_piclist($_POST['tdata_id'], 2, true);
            if ($pic_list && app()->check_right('r_photo_li'))
                $this->piclist_process($pic_list, $head_page_data['use_ml']);
        }
        $this->trigger_execute('afterListItemEdit', $_POST['tdata_id']);
    }

    /**
     * model_cmain::pmove()
     * 
     * @return
     */
    function pmove()
    {
        $data_id = preg_replace('/([a-z]+)(\d*)_(\d*)/', '\1_\3', $_POST['id']);
        list($page_type, $page_id) = explode("_", $data_id);
        if ($page_type == 'pgr') {
            if ($_POST['new_parent'] != 1) {
                $data_id = preg_replace('/([a-z]+)(\d*)_(\d*)/', '\1_\3', $_POST['new_parent']);
                list($npage_type, $npage_id) = explode("_", $data_id);
                $data_id = preg_replace('/([a-z]+)(\d*)_(\d*)/', '\1_\3', $_POST['former_parent']);
                list($fpage_type, $fpage_id) = explode("_", $data_id);
                //if ($npage_type == 'page')
                //die(0);
            }
            else {
                $this->dbh->exec('delete from csct_pgr_link where data_id=' . $page_id);
                $npage_type = 'page';
                $npage_id = 0;
            }

            if ($_POST['new_parent'] != 1) {

                if ($npage_type == "page") {
                    if ($_POST['new_parent'] != $_POST['former_parent']) {
                        $query = "update csct_page_groups set parent_page=" . $npage_id . " where id=" . $page_id;
                        $this->dbh->exec($query);
                    }

                    echo 1;
                }
                else {

                    if ($_POST['new_parent'] != $_POST['former_parent']) {
                        $query = "select id from csct_pgr_link where pg_id=" . $npage_id . " and data_id=" . $page_id;
                        if (!$this->dbh->queryFetchRow($query)) {
                            $query = "select max(num) from csct_pgr_link where pg_id=" . $npage_id;
                            $nr = $this->dbh->queryFetchRow($query);
                            $num = $nr ? current($nr) + 1:1;
                            $query = "insert into csct_pgr_link values ('', '" . $page_id . "', '" . $npage_id . "', '" . $num .
                                "')";
                            $this->dbh->exec($query);
                        }
                    }
                    $qry = "select * from csct_pgr_link where data_id=" . $page_id . " and pg_id=" . $npage_id;
                    $rslt = $this->dbh->queryFetchRow($qry);
                    if ($rslt['num'] < $_POST['position'])
                        $_POST['position']--;

                    $plist = $this->dbh->queryFetchAll("select data_id, num from csct_pgr_link where pg_id=" . $npage_id .
                        " order by num asc");

                    $num = 0;
                    $id = 0;
                    $query = "update csct_pgr_link set num=:num where data_id=:id and pg_id=" . $npage_id;
                    $sql = $this->dbh->prepare($query);
                    $sql->bindParam(':num', $num, PDO::PARAM_INT);
                    $sql->bindParam(':id', $id, PDO::PARAM_INT);
                    $ppr = false;
                    foreach ($plist as $page) {
                        if ($page['data_id'] == $page_id)
                            continue;
                        $id = $page['data_id'];
                        if ($num == $_POST['position']) {
                            $ppr = true;
                            $id = $page_id;
                            $sql->execute();
                            $sql->closeCursor();
                            $num++;
                            $id = $page['data_id'];
                        }
                        $sql->execute();
                        $sql->closeCursor();
                        $num++;
                    }
                    if (!$ppr) {
                        $id = $page_id;
                        $sql->execute();
                        $sql->closeCursor();
                    }
                    echo 1;
                }
            }
            else {
                $plist = $this->dbh->queryFetchAll("select id, num from csct_page_groups where id not in (select data_id from csct_pgr_link) order by num asc");
                $qry = "select * from csct_page_groups where id=" . $page_id;
                $rslt = $this->dbh->queryFetchRow($qry);
                if ($rslt['num'] < $_POST['position'])
                    $_POST['position']--;
                $num = 0;
                $id = 0;
                $query = "update csct_page_groups set num=:num where id=:id";
                $sql = $this->dbh->prepare($query);
                $sql->bindParam(':num', $num, PDO::PARAM_INT);
                $sql->bindParam(':id', $id, PDO::PARAM_INT);
                $ppr = false;
                foreach ($plist as $page) {
                    if ($page['id'] == $page_id)
                        continue;
                    $id = $page['id'];
                    if ($num == $_POST['position']) {
                        $ppr = true;
                        $id = $page_id;
                        //trigger_error("update csct_page_groups set num=$num where id=$id");
                        $sql->execute();
                        $sql->closeCursor();
                        $num++;
                        $id = $page['id'];
                    }
                    //trigger_error("update csct_page_groups set num=$num where id=$id");
                    $sql->execute();
                    $sql->closeCursor();
                    $num++;
                }
                if (!$ppr) {
                    $id = $page_id;
                    //trigger_error("update csct_page_groups set num=$num where id=$id");
                    $sql->execute();
                    $sql->closeCursor();
                }

                //$this->dbh->exec("update csct_pages set num=num+1 where " . ($_POST['new_parent'] != 1 ?
                //    "parent_type=" . ($npage_type == 'page' ? 0:1) . " and parent=" . $npage_id:"parent=0") .
                //    " and num>" . $_POST['position']);

                echo 1;
            }

        }
        else {
            $conditions = array();

            if ($_POST['new_parent'] != 1) {
                $data_id = preg_replace('/([a-z]+)(\d*)_(\d*)/', '\1_\3', $_POST['new_parent']);
                list($npage_type, $npage_id) = explode("_", $data_id);
                $data_id = preg_replace('/([a-z]+)(\d*)_(\d*)/', '\1_\3', $_POST['former_parent']);
                list($fpage_type, $fpage_id) = explode("_", $data_id);
            }
            else {
                $npage_type = 'page';
                $npage_id = 0;
            }

            //привязка к страницам
            if ($npage_type == "page") {
                if ($_POST['new_parent'] != $_POST['former_parent']) {
                    $query = "update csct_pages set parent=" . $npage_id . " where id=" . $page_id;
                    $this->dbh->exec($query);
                }

                $plist = $this->dbh->queryFetchAll("select id, num from csct_pages where " . ($_POST['new_parent'] !=
                    1 ? "parent=" . $npage_id:"parent=0") . " order by num asc");
                $num = 0;
                $id = 0;
                $query = "update csct_pages set num=:num where id=:id";
                $sql = $this->dbh->prepare($query);
                $sql->bindParam(':num', $num, PDO::PARAM_INT);
                $sql->bindParam(':id', $id, PDO::PARAM_INT);
                if ($_POST['new_parent'] == 1) {
                    $query = "select count(id) from csct_page_groups where id not in (select data_id from csct_pgr_link)";
                    $offset = current($this->dbh->queryFetchRow($query));
                }
                elseif ($npage_type == 'pgr') {
                    $query = "select count(id) from csct_page_groups where id = (select data_id from csct_pgr_link where pg_id=" .
                        $npage_id . ")";
                    $offset = current($this->dbh->queryFetchRow($query));
                }
                else {
                    $query = "select count(id) from csct_page_groups where parent_page = " . $npage_id;
                    $offset = current($this->dbh->queryFetchRow($query));
                }
                $qry = "select * from csct_pages where id=" . $page_id;
                $rslt = $this->dbh->queryFetchRow($qry);
                if ($rslt['num'] < $_POST['position'])
                    $_POST['position']--;
                $pos = $_POST['position'] - $offset;
                //trigger_error($rslt['num'].":".$pos . ":" . $_POST['position']);
                $ppr = false;
                foreach ($plist as $page) {
                    if ($page['id'] == $page_id)
                        continue;
                    $id = $page['id'];

                    if ($num == $pos) {
                        $ppr = true;
                        $id = $page_id;
                        //trigger_error("update csct_pages set num=$num where id=$id");
                        $sql->execute();
                        $sql->closeCursor();
                        $num++;
                        $id = $page['id'];
                    }
                    //trigger_error("update csct_pages set num=$num where id=$id");
                    $sql->execute();
                    $sql->closeCursor();
                    $num++;
                }
                if (!$ppr) {
                    $id = $page_id;
                    //trigger_error("update csct_pages set num=$num where id=$id");
                    $sql->execute();
                    $sql->closeCursor();
                }

                //$this->dbh->exec("update csct_pages set num=num+1 where " . ($_POST['new_parent'] != 1 ?
                //    "parent_type=" . ($npage_type == 'page' ? 0:1) . " and parent=" . $npage_id:"parent=0") .
                //    " and num>" . $_POST['position']);

                echo 1;
            }
            else {

                //$query = "update csct_pages set parent=0 where id=" . $page_id;
                //$this->dbh->exec($query);

                if ($_POST['new_parent'] != $_POST['former_parent']) {
                    $query = "select id from csct_pg_link where pg_id=" . $npage_id . " and data_id=" . $page_id;
                    if (!$this->dbh->queryFetchRow($query)) {
                        $query = "select max(num) from csct_pg_link where pg_id=" . $npage_id;
                        $nr = $this->dbh->queryFetchRow($query);
                        $num = $nr ? current($nr) + 1:1;
                        $query = "insert into csct_pg_link values ('', '" . $page_id . "', '" . $npage_id . "', '" . $num .
                            "')";
                        $this->dbh->exec($query);
                    }
                }

                $plist = $this->dbh->queryFetchAll("select data_id, num from csct_pg_link where pg_id=" . $npage_id .
                    " order by num asc");
                $qry = "select * from csct_pg_link where data_id=" . $page_id . " and pg_id=" . $npage_id;
                $rslt = $this->dbh->queryFetchRow($qry);
                if ($rslt['num'] < $_POST['position'])
                    $_POST['position']--;
                $num = 0;
                $id = 0;
                $query = "update csct_pg_link set num=:num where data_id=:id and pg_id=" . $npage_id;
                $sql = $this->dbh->prepare($query);
                $sql->bindParam(':num', $num, PDO::PARAM_INT);
                $sql->bindParam(':id', $id, PDO::PARAM_INT);
                $ppr = false;
                foreach ($plist as $page) {
                    if ($page['data_id'] == $page_id)
                        continue;
                    $id = $page['data_id'];
                    if ($num == $_POST['position']) {
                        $ppr = true;
                        $id = $page_id;
                        $sql->execute();
                        $sql->closeCursor();
                        $num++;
                        $id = $page['data_id'];
                    }
                    $sql->execute();
                    $sql->closeCursor();
                    $num++;
                }
                if (!$ppr) {
                    $id = $page_id;
                    $sql->execute();
                    $sql->closeCursor();
                }
                echo 1;
            }
        }
    }

    /**
     * model_cmain::pgr_process()
     * 
     * @param mixed $page_data
     * @return
     */
    function pgr_process($page_data)
    {
        $this->trigger_execute('beforePageGroupEdit', $_POST['tdata_id']);
        $query = "update csct_page_groups set plink=:plink, dtmpl_id=:dtmpl_id, parent_page=" . ($_POST['parent_page'] ?
            $_POST['parent_page']:0);
        if (app()->ml)
            $query .= ", use_ml=" . $_POST['use_ml'];
        if (app()->md) {
            $query .= ", use_md=" . $_POST['use_md'];

            if (app()->md && isset($_POST['use_md']) && $_POST['use_md'] && isset($_POST['site_id'])) {
                $squery = $this->get_sites($_POST['tdata_id']);
                $site_id = 0;
                $sq = "select id from csct_site_links where data_type=1 and data_id=" . $_POST['tdata_id'] .
                    " and site_id=:site_id";
                $ssql = $this->dbh->prepare($sq);
                $ssql->bindParam(':site_id', $site_id, PDO::PARAM_INT);
                foreach ($squery['sites'] as $site) {
                    if (in_array($site['id'], $_POST['site_id'])) {
                        $site_id = $site['id'];
                        $ssql->execute();
                        $rsd = $ssql->fetch();
                        $ssql->closeCursor();
                        if (!$rsd)
                            $this->dbh->exec("insert into csct_site_links (data_id, data_type, site_id) values ('" . $_POST['tdata_id'] .
                                "', '1', '" . $site_id . "')");
                    }
                    else
                        $this->dbh->exec("delete from csct_site_links where data_type=1 and data_id=" . $_POST['tdata_id'] .
                            " and site_id=" . $site['id']);
                }
            }
            else
                $this->dbh->exec("delete from csct_site_links where data_type=1 and data_id=" . $_POST['tdata_id']);

        }
        $query .= " where id=" . $_POST['tdata_id'];
        $sql = $this->dbh->prepare($query);
        $dtmpl_id = isset($_POST['dtmpl_id']) ? $_POST['dtmpl_id']:0;
        $plink = isset($_POST['plink']) ? $_POST['plink']:0;
        $sql->bindParam(':dtmpl_id', $dtmpl_id, PDO::PARAM_INT);
        $sql->bindParam(':plink', $plink, PDO::PARAM_INT);
        $sql->execute();
        $sql->closeCursor();

        $pg_id = 0;
        $num = 1;
        $query = "insert into csct_pgr_link values ('', '" . $_POST['tdata_id'] . "', :pg_id, :num)";
        $pg_sql = $this->dbh->prepare($query);
        $pg_sql->bindParam(':pg_id', $pg_id, PDO::PARAM_INT);
        $pg_sql->bindParam(':num', $num, PDO::PARAM_INT);
        //$query = "select id from csct_pg_link where data_id=" . $_POST['tdata_id'] . " and pg_id=:pg_id";
        //$pg_isql = $this->dbh->prepare($query);
        //$pg_isql->bindParam(':pg_id', $pg_id, PDO::PARAM_INT);
        $query = "delete from csct_pgr_link where data_id=" . $_POST['tdata_id'] . " and pg_id=:pg_id";
        $pg_dsql = $this->dbh->prepare($query);
        $pg_dsql->bindParam(':pg_id', $pg_id, PDO::PARAM_INT);
        $query = "select max(num) from csct_pgr_link where pg_id=:pg_id";
        $pg_nsql = $this->dbh->prepare($query);
        $pg_nsql->bindParam(':pg_id', $pg_id, PDO::PARAM_INT);
        $query = "select pg_id from csct_pgr_link where data_id=" . $_POST['tdata_id'];
        $exist_pgl = $this->dbh->query($query)->fetchAll(PDO::FETCH_COLUMN);
        if ($exist_pgl) {
            foreach ($exist_pgl as $key => $val) {
                if (isset($_POST['page_group']) && $_POST['page_group']) {
                    foreach ($_POST['page_group'] as $nkey => $nval) {
                        if ($val == $nval) {
                            unset($_POST['page_group'][$nkey]);
                            unset($exist_pgl[$key]);
                            break;
                        }
                    }
                }
            }
        }
        //новые
        if (isset($_POST['page_group']) && $_POST['page_group']) {
            foreach ($_POST['page_group'] as $pg_id) {
                $pg_nsql->execute();
                $nr = $pg_nsql->fetch();
                $num = $nr ? current($pg_nsql) + 1:1;
                $pg_nsql->closeCursor();
                $pg_sql->execute();
                $pg_sql->closeCursor();
            }
        }
        //удаляем старые
        if ($exist_pgl) {
            foreach ($exist_pgl as $pg_id) {
                $pg_dsql->execute();
                $pg_dsql->closeCursor();
            }
        }

        if ($this->registry['user_settings']['acl'] == 2) {
            $this->dbh->exec('delete from csct_userlinks where data_type=1 and data_id=' . $_POST['tdata_id']);
            if ($_POST['ousers'])
                foreach ($_POST['ousers'] as $user)
                    $this->dbh->exec("insert into csct_userlinks values ('', '" . $_POST['tdata_id'] . "', '1', '" . $user .
                        "')");
        }

        $lid = 0;
        $name = '';

        $query = "select count(id) from csct_page_groups_names where data_id=" . $_POST['tdata_id'] .
            " and lang_id=:lid";
        $isset_sql = $this->dbh->prepare($query);
        $isset_sql->bindParam(':lid', $lid, PDO::PARAM_INT);
        $query = "update csct_page_groups_names set name=:name where data_id=" . $_POST['tdata_id'] .
            " and lang_id=:lid";
        $upd_sql = $this->dbh->prepare($query);
        $upd_sql->bindParam(':lid', $lid, PDO::PARAM_INT);
        $upd_sql->bindParam(':name', $name, PDO::PARAM_STR);

        $query = "insert into csct_page_groups_names values ('', '" . $_POST['tdata_id'] . "', :lid, :name)";
        $ins_sql = $this->dbh->prepare($query);
        $ins_sql->bindParam(':lid', $lid, PDO::PARAM_INT);
        $ins_sql->bindParam(':name', $name, PDO::PARAM_STR);
        //$query = "select * from csct_page_groups_names where data_id=" . $_POST['tdata_id'] . " and lang_id=:lid";
        //$sel_sql = $this->dbh->prepare($query);
        //$sel_sql->bindParam(':lid', $lid, PDO::PARAM_INT);

        if ($page_data['use_ml'] && app()->ml) {
            foreach (app()->csct_langs as $lid => $lang_name) {
                $isset_sql->execute();
                $isset_lid = current($isset_sql->fetch());
                $isset_sql->closeCursor();
                $name = $_POST['name_' . $lid];
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
            $name = $_POST['name'];
            if ($isset_lid) {
                $upd_sql->execute();
                $upd_sql->closeCursor();
            }
            else {
                $ins_sql->execute();
                $ins_sql->closeCursor();
            }
        }

        if (app()->ml && isset($_POST['use_ml'])) {
            //$use_ml = isset($_POST['use_ml']) ? 1:0;
            if ($page_data['use_ml'] != $_POST['use_ml']) {
                $queries = array();
                if ($use_ml) {
                    $queries[] = "update csct_page_groups_names set lang_id=" . app()->lang_main . " where data_id=" . $_POST['tdata_id'];
                    $queries[] = "update csct_tdata_fields set lang_id=" . app()->lang_main . " where data_id=" . $_POST['tdata_id'] .
                        " and field_id in (select id from csct_dtmpl_fields where dtmpl_id=" . $_POST['dtmpl_id'] . ")";
                    $queries[] = "update csct_tgdata_fields set lang_id=" . app()->lang_main . " where data_id=" . $_POST['tdata_id'] .
                        " and field_id in (select id from csct_dtmpl_fields where dtmpl_id=" . $_POST['dtmpl_id'] . ")";
                }
                else {
                    $queries[] = "update csct_page_groups_names set lang_id=0 where data_id=" . $_POST['tdata_id'] .
                        " and lang_id=" . app()->lang_main;
                    $queries[] = "update csct_tdata_fields set lang_id=0 where data_id=" . $_POST['tdata_id'] .
                        " and field_id in (select id from csct_dtmpl_fields where dtmpl_id=" . $_POST['dtmpl_id'] . ")" .
                        " and lang_id=" . app()->lang_main;
                    $queries[] = "update csct_tgdata_fields set lang_id=0 where data_id=" . $_POST['tdata_id'] .
                        " and field_id in (select id from csct_dtmpl_fields where dtmpl_id=" . $_POST['dtmpl_id'] . ")" .
                        " and lang_id=" . app()->lang_main;
                }
                foreach ($queries as $qry)
                    $this->dbh->exec($qry);
            }
        }
        $this->trigger_execute('afterPageGroupEdit', $_POST['tdata_id']);
    }

    /**
     * model_cmain::tdata_upload()
     * 
     * @return
     */
    function tdata_upload()
    {
        $tempFile = $_FILES['Filedata']['tmp_name'];
        $targetPath = $_SERVER['DOCUMENT_ROOT'] . '/userfiles/files/';
        $filename = time() . "_" . $this->trlit($_FILES['Filedata']['name'], true);
        $targetFile = rtrim($targetPath, '/') . '/' . $filename;
        move_uploaded_file($tempFile, $targetFile);
        echo $filename;
    }

    /**
     * model_cmain::del_litem()
     * 
     * @return
     */
    function del_litem()
    {
        $this->trigger_execute('beforeListItemDelete', $_POST['id']);
        $query = "delete from csct_list_items_text where data_id=" . $_POST['id'];
        $this->dbh->exec($query);
        $query = "delete from csct_list_items where id=" . $_POST['id'];
        $this->dbh->exec($query);
        $this->trigger_execute('afterListItemDelete', $_POST['id']);
    }

    /**
     * model_cmain::add_li()
     * 
     * @return
     */
    function add_li()
    {
        $this->trigger_execute('beforeListItemCreate');
        $nquery = "select max(num) from csct_list_items where parent_id=" . $_POST['tdata_id'];
        $nr = $this->dbh->queryFetchRow($nquery);
        $num = $nr ? current($nr) + 1:1;
        $query = "insert into csct_list_items (user_id, parent_id, crtime, edtime, ed_user_id, dateofpub, phg_mheight, num, status) values ('" .
            $this->registry->user_id . "', '" . $_POST['tdata_id'] . "', NOW(), NOW(), '" . $this->registry->
            user_id . "', NOW(), '150', '" . $num . "', '0')";
        $this->dbh->exec($query);
        $id = $this->dbh->lastInsertId();
        $page_data = $this->get_page_data($_POST['tdata_id'], false);
        $header = isset($_POST['inName']) ? $_POST['inName']:"";
        if (app()->ml && $page_data['use_ml']) {
            foreach (app()->langs as $lid => $ln)
                $this->dbh->exec("insert into csct_list_items_text (data_id, lang_id, header) values ('" . $id .
                    "', '" . $lid . "', '" . $header . "')");
        }
        else
            $this->dbh->exec("insert into csct_list_items_text (data_id, lang_id, header) values ('" . $id .
                "', '0', '" . $header . "')");
        $this->trigger_execute('afterListItemCreate', $id);
        return $id;
    }

    /**
     * model_cmain::piclist_process()
     * 
     * @param mixed $pic_list
     * @return
     */
    function piclist_process($pic_list, $use_ml)
    {
        $id = 0;
        $header = '';
        $mark = '';
        $address = '';
        $file_link = '';
        $alttext = '';
        $active = 1;
        $ltype = 0;
        $lpage = 0;
        $link_id = 0;
        $query = "insert into csct_pics_text values ('', :id, :lid, :alttext)";
        $ins_sql = $this->dbh->prepare($query);
        $ins_sql->bindParam(':lid', $lid, PDO::PARAM_INT);
        $ins_sql->bindParam(':alttext', $alttext, PDO::PARAM_STR);
        $ins_sql->bindParam(':id', $id, PDO::PARAM_STR);

        $query = "update csct_pics set address=:address, ltype=:ltype, lpage=:lpage where id=:id";
        $usql = $this->dbh->prepare($query);
        $usql->bindParam(':id', $id, PDO::PARAM_INT);
        $usql->bindParam(':address', $address, PDO::PARAM_STR);
        $usql->bindParam(':ltype', $ltype, PDO::PARAM_INT);
        $usql->bindParam(':lpage', $lpage, PDO::PARAM_INT);

        $query = "update csct_pics_text set alttext=:alttext where data_id=:id and lang_id=:lid";
        $upd_sql = $this->dbh->prepare($query);
        $upd_sql->bindParam(':lid', $lid, PDO::PARAM_INT);
        $upd_sql->bindParam(':id', $id, PDO::PARAM_INT);
        $upd_sql->bindParam(':alttext', $alttext, PDO::PARAM_STR);

        $query = "select * from csct_pics_text where data_id=:id and lang_id=:lid";
        $sel_sql = $this->dbh->prepare($query);
        $sel_sql->bindParam(':lid', $lid, PDO::PARAM_INT);
        $sel_sql->bindParam(':id', $id, PDO::PARAM_INT);

        $query = "delete from csct_pic_links where pic_id=:id";
        $pldsql = $this->dbh->prepare($query);
        $pldsql->bindParam(':id', $id, PDO::PARAM_INT);

        $query = "insert into csct_pic_links (pic_id, link_id) values (:id, :link_id)";
        $plisql = $this->dbh->prepare($query);
        $plisql->bindParam(':id', $id, PDO::PARAM_INT);
        $plisql->bindParam(':link_id', $link_id, PDO::PARAM_INT);

        foreach ($pic_list as $pic) {
            $id = $pic['id'];
            $ltype = 0;
            $lpage = 0;

            $address = $_POST['paddr_' . $pic['id']];
            if ($_POST['plink_' . $pic['id']])
                list($ltype, $lpage) = explode("_", $_POST['plink_' . $pic['id']]);
            $usql->execute();
            $usql->closeCursor();

            if (app()->ml && $use_ml) {
                foreach (app()->csct_langs as $lid => $lang_name) {
                    $sel_sql->execute();
                    $text_data = $sel_sql->fetch();
                    $sel_sql->closeCursor();
                    $alttext = $_POST['alttext_' . $pic['id'] . '_' . $lid];

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
                $alttext = $_POST['alttext_' . $pic['id'] . '_0'];
                if ($text_data) {
                    $upd_sql->execute();
                    $upd_sql->closeCursor();
                }
                else {
                    $ins_sql->execute();
                    $ins_sql->closeCursor();
                }
            }
            $pldsql->execute();
            $pldsql->closeCursor();
            if (isset($_POST['pic_link_' . $id]) && $_POST['pic_link_' . $id]) {
                foreach ($_POST['pic_link_' . $id] as $link_id) {
                    $plisql->execute();
                    $plisql->closeCursor();
                }
            }
        }
    }

    /**
     * model_cmain::get_piclist()
     * 
     * @param mixed $tdata_id
     * @param mixed $data_type
     * @param bool $simple
     * @return
     */
    function get_piclist($tdata_id, $data_type, $simple = false, $main = false)
    {
        $sel_query = "select * from csct_pics where data_id='" . $tdata_id . "' and data_type=" . $data_type;
        if ($main)
            $sel_query .= " and main_pic=1";
        $sel_query .= " order by num asc";
        $phdata = $this->dbh->queryFetchAll($sel_query);

        if (!$simple) {
            $lpage = 0;
            $query = "select header from csct_pages_text where data_id=:lpage";
            $psql = $this->dbh->prepare($query);
            $psql->bindParam(':lpage', $lpage, PDO::PARAM_INT);
            $query = "select header from csct_list_items_text where data_id=:lpage";
            $lpsql = $this->dbh->prepare($query);
            $lpsql->bindParam(':lpage', $lpage, PDO::PARAM_INT);
            $query = "select * from csct_pics_text where data_id=:id";
            $id = 0;
            $sql = $this->dbh->prepare($query);
            $sql->bindParam(':id', $id, PDO::PARAM_INT);
            $query = "select link_id from csct_pic_links where pic_id=:id";
            $plsql = $this->dbh->prepare($query);
            $plsql->bindParam(':id', $id, PDO::PARAM_INT);
            $query = "select nt.name, nt.data_id from csct_lib_content_names nt, csct_pic_links pl, csct_tdata_flib fl where pl.pic_id=:id and fl.item_id=nt.data_id and fl.id=pl.link_id";
            $plesql = $this->dbh->prepare($query);
            $plesql->bindParam(':id', $id, PDO::PARAM_INT);
            foreach ($phdata as $key => $item) {
                $text_data = array();
                $id = $item['id'];
                $lpage = $item['lpage'];
                if ($lpage) {
                    if ($item['ltype'] == 0) {
                        $psql->execute();
                        $pdata = $psql->fetch();
                        $psql->closeCursor();
                        $phdata[$key]['plink'] = '0_' . $item['lpage'];
                        $phdata[$key]['plink_name'] = $pdata['header'];
                    }
                    else {
                        $lpsql->execute();
                        $pdata = $lpsql->fetch();
                        $lpsql->closeCursor();
                        $phdata[$key]['plink'] = '1_' . $item['lpage'];
                        $phdata[$key]['plink_name'] = $pdata['header'];
                    }
                }
                $sql->execute();
                $tdata = $sql->fetchAll();
                $sql->closeCursor();
                foreach ($tdata as $titem)
                    $text_data[$titem['lang_id']] = $titem;
                $phdata[$key]['text'] = $text_data;
                $plsql->execute();
                $phdata[$key]['pic_links'] = $plsql->fetchAll(PDO::FETCH_COLUMN);
                $plsql->closeCursor();
                $plesql->execute();
                $phdata[$key]['pic_links_data'] = $plesql->fetchAll();
                $plesql->closeCursor();
            }
        }
        return $phdata;
    }

    /**
     * model_cmain::pic_upload()
     * 
     * @return
     */
    function pic_upload()
    {
        $targetPath = $_SERVER['DOCUMENT_ROOT'] . '/userfiles/images/';
        $tempFile = $_FILES['Filedata']['tmp_name'];
        $phg_folder = "photogalleries/" . $_REQUEST['tdata_id'] . "_" . $_REQUEST['data_type'] . "/";
        if (!file_exists($targetPath . $phg_folder)) {
            $folders = explode("/", trim($phg_folder, "/"));
            $initial_path = $targetPath;
            foreach ($folders as $folder) {
                mkdir($initial_path . "/" . $folder);
                chmod($initial_path . "/" . $folder);
                $initial_path .= "/" . $folder;
            }
        }
        $filename = $phg_folder . time() . "_" . $this->trlit($_FILES['Filedata']['name'], true);
        $targetFile = $targetPath . $filename;
        $improp = getimagesize($tempFile);
        $width = $improp[0];
        $height = $improp[1];
        if ($_REQUEST['data_type'] == 1)
            $page_data = $this->get_page_data($_REQUEST['tdata_id'], false);
        else
            $page_data = $this->get_lpage_data($_REQUEST['tdata_id'], false);
        //if ($width > 2000 || $height > 2000)
        //die();

        if ($page_data['phg_width'] && !$page_data['phg_height'])
            $scale = $page_data['phg_width'] / $width;
        elseif (!$page_data['phg_width'] && $page_data['phg_height'])
            $scale = $page_data['phg_height'] / $height;

        if ($page_data['phg_mwidth'] && !$page_data['phg_mheight'])
            $mscale = $page_data['phg_mwidth'] / $width;
        elseif (!$page_data['phg_mwidth'] && $page_data['phg_mheight'])
            $mscale = $page_data['phg_mheight'] / $height;
        else {
            if ($width > $height)
                $mscale = 150 / $height;
            else
                $mscale = 150 / $width;
        }

        if ($page_data['phg_width'] && $page_data['phg_height']) {
            $nw = $page_data['phg_width'];
            $nh = $page_data['phg_height'];
        }
        elseif (isset($scale)) {
            $nw = round($width * $scale);
            $nh = round($height * $scale);
        }
        else {
            $nw = $width;
            $nh = $height;
        }

        if ($page_data['phg_mwidth'] && $page_data['phg_mheight']) {
            $thnw = $page_data['phg_mwidth'];
            $thnh = $page_data['phg_mheight'];
        }
        elseif (isset($mscale)) {
            $thnw = round($width * $mscale);
            $thnh = round($height * $mscale);
        }
        else {
            $thnw = $width;
            $thnh = $height;
        }
        /*
        if ($nw > $nh)
        $thscale = 150 / $nh;
        else
        $thscale = 150 / $nw;

        $thnw = round($nw * $thscale);
        $thnh = round($nh * $thscale);
        */
        if ($page_data['phg_wm']) {
            if ($improp['mime'] == 'image/jpeg')
                $srcim = imagecreatefromjpeg($tempFile);
            elseif ($improp['mime'] == 'image/png')
                $srcim = imagecreatefrompng($tempFile);
            if ($improp['mime'] == 'image/gif')
                $srcim = imagecreatefromgif($tempFile);
            $dstim = imagecreatetruecolor($nw, $nh);
            $newim = imagecopyresampled($dstim, $srcim, 0, 0, 0, 0, $nw, $nh, $width, $height);

            $watermarkfile_id = imagecreatefrompng($_SERVER['DOCUMENT_ROOT'] . $page_data['phg_wm']);
            imageAlphaBlending($watermarkfile_id, false);
            imageSaveAlpha($watermarkfile_id, true);
            $sourcefile_width = imageSX($dstim);
            $sourcefile_height = imageSY($dstim);
            $watermarkfile_width = imageSX($watermarkfile_id);
            $watermarkfile_height = imageSY($watermarkfile_id);

            $dest_x = ($sourcefile_width) - ($watermarkfile_width);
            $dest_y = ($sourcefile_height) - ($watermarkfile_height);
            imagecopy($dstim, $watermarkfile_id, $dest_x, $dest_y, 0, 0, $watermarkfile_width, $watermarkfile_height);

            if ($improp['mime'] == 'image/jpeg')
                imagejpeg($dstim, $targetFile, 100);
            elseif ($improp['mime'] == 'image/png')
                imagepng($dstim, $targetFile, 0);
            if ($improp['mime'] == 'image/gif')
                imagegif($dstim, $targetFile, 100);

            imagedestroy($dstim);
        }
        else {
            if ($improp['mime'] == 'image/jpeg')
                $srcim = imagecreatefromjpeg($tempFile);
            elseif ($improp['mime'] == 'image/png') {
                $srcim = imagecreatefrompng($tempFile);
                //imageAlphaBlending($srcim, false);
                //imageSaveAlpha($srcim, true);
            }
            if ($improp['mime'] == 'image/gif')
                $srcim = imagecreatefromgif($tempFile);
            $dstim = imagecreatetruecolor($nw, $nh);
            if ($improp['mime'] == 'image/png') {
                imagealphablending($dstim, false);
                imagesavealpha($dstim, true);
                $transparent = imagecolorallocatealpha($dstim, 255, 255, 255, 127);
                imagefilledrectangle($dstim, 0, 0, $nw, $nh, $transparent);
            }

            $newim = imagecopyresampled($dstim, $srcim, 0, 0, 0, 0, $nw, $nh, $width, $height);
            if ($improp['mime'] == 'image/jpeg')
                imagejpeg($dstim, $targetFile, 100);
            elseif ($improp['mime'] == 'image/png')
                imagepng($dstim, $targetFile, 0);
            if ($improp['mime'] == 'image/gif')
                imagegif($dstim, $targetFile, 100);
        }
        //thumbnail
        if ($improp['mime'] == 'image/jpeg')
            $srcim = imagecreatefromjpeg($tempFile);
        elseif ($improp['mime'] == 'image/png') {
            $srcim = imagecreatefrompng($tempFile);
            imageAlphaBlending($srcim, true);
            imageSaveAlpha($srcim, true);
        }
        if ($improp['mime'] == 'image/gif')
            $srcim = imagecreatefromgif($tempFile);
        $dstim = imagecreatetruecolor($thnw, $thnh);
        if ($improp['mime'] == 'image/png') {
            $transparent = imagecolorallocate($dstim, 0, 0, 0);
            imagecolortransparent($dstim, $transparent);
        }
        $newim = imagecopyresampled($dstim, $srcim, 0, 0, 0, 0, $thnw, $thnh, $width, $height);

        //list($fname, $ext) = explode('.', $filename);
        preg_match('/^(.*)\.([^\.]+)$/U', $filename, $matches);
        //imagejpeg($dstim, $targetPath . $matches[1] . "_thn." . $matches[2]);
        if ($improp['mime'] == 'image/jpeg')
            imagejpeg($dstim, $targetPath . $matches[1] . "_thn." . $matches[2]);
        elseif ($improp['mime'] == 'image/png')
            imagepng($dstim, $targetPath . $matches[1] . "_thn." . $matches[2]);
        if ($improp['mime'] == 'image/gif')
            imagegif($dstim, $targetPath . $matches[1] . "_thn." . $matches[2]);
        imagedestroy($dstim);
        $query = "select max(num) from csct_pics where data_id=" . $_REQUEST['tdata_id'] . " and data_type=" .
            $_REQUEST['data_type'];
        $rslt = $this->dbh->query($query)->fetch();
        $num = $rslt ? (current($rslt) + 1):1;
        $query = "insert into csct_pics (data_id, data_type, file, num) values ('" . $_REQUEST['tdata_id'] .
            "', '" . $_REQUEST['data_type'] . "', '" . $filename . "', '" . $num . "')";
        $this->dbh->exec($query);
        echo 1;

    }

    /**
     * model_cmain::file_uploads()
     * 
     * @param string $sf
     * @return
     */
    function file_uploads($sf = 'files')
    {
        $tempFile = $_FILES['Filedata']['tmp_name'];
        $targetPath = $_SERVER['DOCUMENT_ROOT'] . '/userfiles/' . $sf;
        $filename = time() . "_" . $this->trlit($_FILES['Filedata']['name'], true);
        $targetFile = rtrim($targetPath, '/') . '/' . $filename;
        move_uploaded_file($tempFile, $targetFile);
        echo $filename;
    }

    function file_mfuploads()
    {
        if (!preg_match('/userfiles/i', $_POST['dir']))
            die();
        $tempFile = $_FILES['Filedata']['tmp_name'];
        $targetPath = $_SERVER['DOCUMENT_ROOT'] . $_POST['dir'];
        $filename = time() . "_" . $this->trlit($_FILES['Filedata']['name'], true);
        $targetFile = rtrim($targetPath, '/') . '/' . $filename;
        move_uploaded_file($tempFile, $targetFile);
        echo $filename;
    }

    /**
     * model_cmain::del_pic()
     * 
     * @return
     */
    function del_pic()
    {
        if (!isset($_POST['ph_id']) && isset($_POST['all'])) {
            $query = "select * from csct_pics where data_id=" . $_POST['tdata_id'] . " and data_type=" . $_POST['data_type'];
            $result = $this->dbh->queryFetchAll($query);
            foreach ($result as $item) {
                $filename = $item['file'];
                unlink($_SERVER['DOCUMENT_ROOT'] . '/userfiles/images/' . $filename);
                //list($fname, $ext) = explode('.', $filename);
                preg_match('/^(.*)\.([^\.]+)$/U', $filename, $matches);
                unlink($_SERVER['DOCUMENT_ROOT'] . '/userfiles/images/' . $matches[1] . "_thn." . $matches[2]);
                $this->dbh->exec('delete from csct_pics where id=' . $item['id']);
            }
        }
        else {
            $query = "select file from csct_pics where id=" . $_POST['ph_id'];
            $result = $this->dbh->queryFetchRow($query);
            $filename = current($result);
            unlink($_SERVER['DOCUMENT_ROOT'] . '/userfiles/images/' . $filename);
            //list($fname, $ext) = explode('.', $filename);
            preg_match('/^(.*)\.([^\.]+)$/U', $filename, $matches);
            unlink($_SERVER['DOCUMENT_ROOT'] . '/userfiles/images/' . $matches[1] . "_thn." . $matches[2]);
            $this->dbh->exec('delete from csct_pics where id=' . $_POST['ph_id']);
        }
    }

    /**
     * model_cmain::make_phmain()
     * 
     * @return
     */
    function make_phmain()
    {
        $query = "update csct_pics set main_pic=0 where data_id=" . $_REQUEST['tdata_id'] .
            " and data_type=" . $_REQUEST['data_type'];
        $this->dbh->exec($query);
        $query = "update csct_pics set main_pic=1 where id=" . $_REQUEST['ph_id'];
        $this->dbh->exec($query);
    }

    /**
     * model_cmain::save_div_data()
     * 
     * @return
     */
    function save_div_data()
    {
        $this->dbh->exec("delete from csct_stmpl_data_text where page_id=" . $_POST['tdata_id'] .
            " and page_type=" . $_POST['page_type'] . " and block_id=" . $_POST['block_id']);
        $query = "select * from csct_stmpl_data where id=" . $_POST['tdata_id'];
        $stmpl_data = $this->dbh->query($query)->fetch();
        if ($_POST['page_type'] == 0)
            $query = "select * from csct_pages where id=" . $_POST['tdata_id'];
        else
            $query = "select * from csct_pages where id=(select parent_id from csct_list_items where id=" . $_POST['tdata_id'] .
                ")";
        $hp_data = $this->dbh->query($query)->fetch();
        $div_text = '';
        $lid = 0;

        $query = "insert into csct_stmpl_data_text values ('', '" . $_POST['tdata_id'] . "', '" . $_POST['page_type'] .
            "', '" . $_POST['block_id'] . "', :lid, :div_text)";
        $sql = $this->dbh->prepare($query);
        $sql->bindParam(':lid', $lid, PDO::PARAM_INT);
        $sql->bindParam(':div_text', $div_text, PDO::PARAM_STR);
        if (app()->ml && $hp_data['use_ml']) {
            foreach (app()->csct_langs as $lid => $lang_name) {
                $div_text = preg_replace('/<p>#{(.*)}#<\/p>/i', '#{\1}#', $_POST['div_text_' . $lid]);
                $div_text = preg_replace_callback('|#\{[^#]+\}#|U', array(&$this, "clear_tag"), $div_text);
                $div_text = preg_replace_callback('|\[\*[^\*]+\*\]|U', array(&$this, "clear_tag"), $div_text);
                $sql->execute();
                $sql->closeCursor();
            }
        }
        else {
            $div_text = preg_replace('/<p>#{(.*)}#<\/p>/i', '#{\1}#', $_POST['div_text_0']);
            $div_text = preg_replace_callback('|#\{[^#]+\}#|U', array(&$this, "clear_tag"), $div_text);
            $div_text = preg_replace_callback('|\[\*[^\*]+\*\]|U', array(&$this, "clear_tag"), $div_text);
            $sql->execute();
            $sql->closeCursor();
        }
        $lid = (app()->ml && $hp_data['use_ml']) ? app()->lang_main:0;
        $text = preg_replace('~<script[^>]*>.*?</script>~si', '', $_POST['div_text_' . $lid]);
        echo $text;
    }

    /**
     * model_cmain::set_archive()
     * 
     * @param mixed $data_id
     * @param mixed $data_type
     * @param mixed $pcrtime
     * @param mixed $text
     * @return
     */
    function set_archive($data_id, $data_type, $pcrtime, $text)
    {
        $query = "insert into csct_archive (data_id, crtime, pcrtime, data_type, user_id, adata) values ('" .
            $data_id . "', NOW(), '" . ($pcrtime ? $pcrtime:'NOW()') . "', '" . $data_type . "', '" . $this->
            registry->user_id . "', :adata)";
        $sql = $this->dbh->prepare($query);
        $sql->bindParam(':adata', $text, PDO::PARAM_STR);
        $sql->execute();
        $sql->closeCursor();
    }

    /**
     * model_cmain::get_archive()
     * 
     * @param mixed $data_id
     * @param mixed $data_type
     * @param bool $get_text
     * @param mixed $aid
     * @return
     */
    function get_archive($data_id, $data_type, $get_text = false, $aid = null)
    {
        $query = "select mt.id, DATE_FORMAT(mt.crtime, '%d.%m.%Y %H:%i') fcrtime, DATE_FORMAT(mt.pcrtime, '%d.%m.%Y %H:%i') fpcrtime, u.name user_name" . ($get_text ?
            ", mt.adata":"") . " from csct_archive mt, csct_users u where u.id=mt.user_id and mt.data_id=" . $data_id .
            " and mt.data_type=" . $data_type;
        if ($aid) {
            $query .= " and mt.id=" . $aid;
            return $this->dbh->queryFetchRow($query);
        }
        else {
            $query .= " order by crtime desc";
            return $this->dbh->queryFetchAll($query);
        }
    }

    /**
     * model_cmain::check_time()
     * 
     * @return
     */
    function check_time()
    {
        $dt = @date("Y-m-d H:i:s", $_POST['checktime']);
        $query = "select mt.id, DATE_FORMAT(mt.crtime, '%d.%m.%Y %H:%i:%s') fcrtime, u.name user_name from csct_archive mt, csct_users u where u.id=mt.user_id and mt.data_id=" .
            $_POST['tdata_id'] . " and mt.data_type=" . $_POST['data_type'] . " and crtime>'" . $dt . "'";
        $result = $this->dbh->queryFetchRow($query);
        if (!$result)
            return array('cht' => 0);
        else
            return array(
                'cht' => 1,
                'user' => $result['user_name'],
                'edtime' => $result['fcrtime']);
    }

    /**
     * model_cmain::pgr_copy()
     * 
     * @param mixed $tdata_id
     * @param bool $copy_children
     * @param mixed $nparent
     * @param mixed $ngparent
     * @param mixed $rgpagrent
     * @return
     */
    function pgr_copy($tdata_id, $copy_children = false, $nparent = null, $ngparent = null, $rgpagrent = null)
    {

        if (in_array($tdata_id, $this->pgcopied))
            return null;
        else {
            $this->trigger_execute('beforePageGroupCopy', $tdata_id);
            $this->pgcopied[] = $tdata_id;
            $page_data = $this->get_page_group($tdata_id);
            $nquery = "select max(num) from csct_page_groups where ";
            if ($page_data['page_data']['parent_page'])
                $nquery .= "id in (select id from csct_page_groups where parent_page=" . $page_data['page_data']['parent_page'] .
                    ")";
            else
                $nquery .= "parent_page=0";
            $nr = $this->dbh->queryFetchRow($nquery);
            $num = $nr ? current($nr) + 1:1;
            $fields = array(
                'use_ml',
                'use_md',
                'dtmpl_id',
                'plink',
                'parent_page');
            $query = "insert into csct_page_groups (user_id, ed_user_id, crtime, edtime, num,";
            $query .= join(", ", $fields);
            $query .= ") values ('" . $this->registry->user_id . "', '" . $this->registry->user_id .
                "', NOW(), NOW(), '" . $num . "'";
            foreach ($fields as $key => $value) {
                if ($value == 'parent_page' && $copy_children && $nparent)
                    $query .= ", '" . $nparent . "'";
                else
                    $query .= ", '" . $page_data['page_data'][$value] . "'";
            }
            $query .= ")";
            $this->dbh->exec($query);
            $ntdata_id = $this->dbh->lastInsertId();

            $lang_id = 0;
            $name = '';
            $query = "insert into csct_page_groups_names values ('', '" . $ntdata_id . "', :lang_id, :name)";
            $sql = $this->dbh->prepare($query);
            $sql->bindParam(':lang_id', $lang_id, PDO::PARAM_INT);
            $sql->bindParam(':name', $name, PDO::PARAM_STR);
            foreach ($page_data['text_data'] as $lang_id => $text_data) {
                $name = $copy_children ? $text_data['name']:'Копия ' . $text_data['name'];
                $sql->execute();
                $sql->closeCursor();
            }
            if ($page_data['page_data']['use_md']) {
                $sites = $this->get_sites($tdata_id, 1);
                foreach ($sites['site_data'] as $dom_id => $site_data)
                    $this->dbh->exec("insert into csct_site_links values ('', '" . $ntdata_id . "', '1', '" . $dom_id .
                        "', '" . $site_data['template'] . "', '" . $site_data['page_snp'] . "', '" . $site_data['snp_list'] .
                        "', '" . $site_data['snp_list_item'] . "')");
            }

            $query = "select * from csct_pgr_link where data_id=" . $tdata_id;
            $result = $this->dbh->queryFetchAll($query);
            if ($result) {
                $pg_id = 0;
                $num = 1;
                $query = "insert into csct_pgr_link values ('', '" . $ntdata_id . "', :pg_id, :num)";
                $pg_sql = $this->dbh->prepare($query);
                $pg_sql->bindParam(':pg_id', $pg_id, PDO::PARAM_INT);
                $pg_sql->bindParam(':num', $num, PDO::PARAM_INT);

                $query = "select max(num) from csct_pgr_link where pg_id=:pg_id";
                $pg_nsql = $this->dbh->prepare($query);
                $pg_nsql->bindParam(':pg_id', $pg_id, PDO::PARAM_INT);
                foreach ($result as $item) {
                    $pg_id = $item['pg_id'];
                    if ($ngparent !== null && $rgpagrent !== null && $pg_id == $rgpagrent)
                        $pg_id = $ngparent;
                    $pg_nsql->execute();
                    $nr = $pg_nsql->fetch();
                    $num = $nr ? current($nr) + 1:1;
                    $pg_nsql->closeCursor();
                    $pg_sql->execute();
                    $pg_sql->closeCursor();
                }

            }
            if ($page_data['page_data']['dtmpl_id'] != 0)
                $this->copy_dtmpl_data($tdata_id, $ntdata_id, $page_data['page_data']['dtmpl_id']);

            if ($copy_children) {
                $sgroups = $this->get_subgroups($tdata_id);
                if ($sgroups) {
                    foreach ($sgroups as $sgroup)
                        $this->pgr_copy($sgroup['id'], true, $nparent, $ntdata_id, $tdata_id);
                }
                $spages = $this->get_group_pages($tdata_id);
                if ($spages) {
                    foreach ($spages as $spage)
                        $this->page_copy($spage['id'], true, $nparent, $ntdata_id, $tdata_id);
                }
            }
            $this->trigger_execute('afterPageGroupCopy', $ntdata_id);
            return $ntdata_id;
        }
    }

    /**
     * model_cmain::page_copy()
     * 
     * @param mixed $tdata_id
     * @param bool $copy_children
     * @param mixed $nparent
     * @param mixed $ngparent
     * @param mixed $rgpagrent
     * @param mixed $oparent
     * @return
     */
    function page_copy($tdata_id, $copy_children = false, $nparent = null, $ngparent = null, $rgpagrent = null,
        $oparent = null)
    {

        if (in_array($tdata_id, $this->pcopied))
            return null;
        else {
            $this->trigger_execute('beforePageCopy', $tdata_id);
            $this->pcopied[] = $tdata_id;
            $page_data = $this->get_page_data($tdata_id);
            $nquery = "select max(num) from csct_pages where ";
            if ($page_data['page_data']['parent'])
                $nquery .= "id in (select id from csct_pages where parent=" . $page_data['page_data']['parent'] .
                    ")";
            else
                $nquery .= "parent=0";
            $nr = $this->dbh->queryFetchRow($nquery);
            $num = $nr ? current($nr) + 1:1;
            $fields = array(
                'use_ml',
                'use_md',
                'use_photo',
                'template',
                'subtemplate',
                'dtmpl_id',
                'lib_id',
                'dtmpl_id_lc',
                'snp_list',
                'snp_list_item',
                'page_snp',
                'photo_snp',
                'db_type',
                'plink',
                'ltype',
                'file_link',
                'use_preview',
                'parent',
                'archive',
                'service',
                'p_access',
                'show_menu',
                'sorting',
                'sort_reverse',
                'kw_type',
                'mdescr_type',
                'phg_width',
                'phg_height',
                'phg_mwidth',
                'phg_mheight',
                'phg_wm',
                'use_comments',
                'moderation');
            $query = "insert into csct_pages (user_id, ed_user_id, crtime, edtime, address, num,";
            $query .= join(", ", $fields);
            $query .= ") values ('" . $this->registry->user_id . "', '" . $this->registry->user_id .
                "', NOW(), NOW(), '" . ($page_data['page_data']['address'] ? ($copy_children ? $page_data['page_data']['address']:
                "copy_" . $page_data['page_data']['address']):"") . "', '" . $num . "'";
            foreach ($fields as $key => $value) {
                if ($value == 'parent' && $copy_children && $nparent && $page_data['page_data'][$value] == $oparent)
                    $query .= ", '" . $nparent . "'";
                else
                    $query .= ", '" . $page_data['page_data'][$value] . "'";
            }
            $query .= ")";
            $this->dbh->exec($query);
            $ntdata_id = $this->dbh->lastInsertId();
            $query = "select * from csct_constants where data_type=1 and data_id=" . $tdata_id;
            $cResult = $this->dbh->queryFetchAll($query);
            if ($cResult) {
                foreach ($cResult as $cnstData)
                    $this->dbh->exec("insert into csct_constants (data_id, data_type, ckey, cvalue) values ('" . $ntdata_id .
                        "', '1', '" . $cnstData['ckey'] . "', '" . $cnstData['cvalue'] . "')");
            }

            $lang_id = 0;
            $header = '';
            $subheader = '';
            $title = '';
            $page_text = '';
            $menu_name = '';
            $seo_kw = '';
            $seo_descr = '';
            $query = "insert into csct_pages_text values ('', '" . $ntdata_id .
                "', :lang_id, :header, :subheader, :title, :page_text, :menu_name, :seo_kw, :seo_descr)";
            $sql = $this->dbh->prepare($query);
            $sql->bindParam(':lang_id', $lang_id, PDO::PARAM_INT);
            $sql->bindParam(':header', $header, PDO::PARAM_STR);
            $sql->bindParam(':subheader', $subheader, PDO::PARAM_STR);
            $sql->bindParam(':title', $title, PDO::PARAM_STR);
            $sql->bindParam(':page_text', $page_text, PDO::PARAM_STR);
            $sql->bindParam(':menu_name', $menu_name, PDO::PARAM_STR);
            $sql->bindParam(':seo_kw', $seo_kw, PDO::PARAM_STR);
            $sql->bindParam(':seo_descr', $seo_descr, PDO::PARAM_STR);
            foreach ($page_data['text_data'] as $lang_id => $text_data) {
                $header = $copy_children ? $text_data['header']:'Копия ' . $text_data['header'];
                //$header = 'Копия ' . $text_data['header'];
                $subheader = $text_data['subheader'];
                $title = $text_data['title'];
                $page_text = $text_data['page_text'];
                $menu_name = $text_data['menu_name'];
                $seo_kw = $text_data['seo_kw'];
                $seo_descr = $text_data['seo_descr'];
                $sql->execute();
                $sql->closeCursor();
            }
            if ($page_data['page_data']['use_md']) {
                $sites = $this->get_sites($tdata_id);
                foreach ($sites['site_data'] as $dom_id => $site_data)
                    $this->dbh->exec("insert into csct_site_links values ('', '" . $ntdata_id . "', '0', '" . $dom_id .
                        "', '" . $site_data['template'] . "', '" . $site_data['page_snp'] . "', '" . $site_data['snp_list'] .
                        "', '" . $site_data['snp_list_item'] . "')");
            }

            $query = "select * from csct_pg_link where data_id=" . $tdata_id;
            $result = $this->dbh->queryFetchAll($query);
            if ($result) {
                $pg_id = 0;
                $num = 1;
                $query = "insert into csct_pg_link values ('', '" . $ntdata_id . "', :pg_id, :num)";
                $pg_sql = $this->dbh->prepare($query);
                $pg_sql->bindParam(':pg_id', $pg_id, PDO::PARAM_INT);
                $pg_sql->bindParam(':num', $num, PDO::PARAM_INT);

                $query = "select max(num) from csct_pg_link where pg_id=:pg_id";
                $pg_nsql = $this->dbh->prepare($query);
                $pg_nsql->bindParam(':pg_id', $pg_id, PDO::PARAM_INT);
                foreach ($result as $item) {
                    $pg_id = $item['pg_id'];
                    if ($ngparent !== null && $rgpagrent !== null && $pg_id == $rgpagrent)
                        $pg_id = $ngparent;
                    $pg_nsql->execute();
                    $nr = $pg_nsql->fetch();
                    $num = $nr ? current($nr) + 1:1;
                    $pg_nsql->closeCursor();
                    $pg_sql->execute();
                    $pg_sql->closeCursor();
                }

            }

            if ($page_data['page_data']['subtemplate'] != 0)
                $this->copy_stmpl($tdata_id, $ntdata_id, 0);

            if ($page_data['page_data']['dtmpl_id'] != 0)
                $this->copy_dtmpl_data($tdata_id, $ntdata_id, $page_data['page_data']['dtmpl_id']);

            if ($copy_children) {
                if ($copy_children == 2 && $page_data['page_data']['db_type'] == 1) {
                    $lplist = $this->lp_list($tdata_id, null, null);
                    foreach ($lplist['data'] as $li)
                        $this->lc_copy($li['id'], $tdata_id, $ntdata_id);
                }
                $sgroups = $this->get_pagesubgroups($tdata_id);
                if ($sgroups) {
                    foreach ($sgroups as $sgroup)
                        $this->pgr_copy($sgroup['id'], true, $ntdata_id, $ngparent, $rgpagrent);
                }
                $spages = $this->get_subpages($tdata_id);
                if ($spages) {
                    foreach ($spages as $spage)
                        $this->page_copy($spage['id'], true, $ntdata_id, $ngparent, $rgpagrent, $tdata_id);
                }
            }
            $this->trigger_execute('afterPageCopy', $ntdata_id);
            return $ntdata_id;
        }

    }

    /**
     * model_cmain::lc_copy()
     * 
     * @param mixed $tdata_id
     * @param mixed $parent_id
     * @return
     */
    function lc_copy($tdata_id, $parent_id, $nparent_id = null)
    {
        $tpage_data = $this->get_page_data($parent_id, false);
        $page_data = $this->get_lpage($tdata_id, $parent_id);
        $nquery = "select max(num) from csct_list_items where parent_id=" . ($nparent_id ? $nparent_id:$parent_id);
        $nr = $this->dbh->queryFetchRow($nquery);
        $num = $nr ? current($nr) + 1:1;

        $fields = array(
            'subtemplate',
            'template',
            'db_type',
            'plink',
            'ltype',
            'archive',
            'file_link',
            'parent_id',
            'kw_type',
            'mdescr_type',
            'dateofpub',
            'priority',
            'photo_snp',
            'use_photo',
            'phg_width',
            'phg_height',
            'phg_mwidth',
            'phg_mheight',
            'phg_wm',
            'use_comments',
            'moderation');
        $query = "insert into csct_list_items (user_id, ed_user_id, crtime, edtime, address, num,";
        $query .= join(", ", $fields);
        $address = $page_data['address'] ? (!$nparent_id ? "copy_":"") . $page_data['address']:"";
        $query .= ") values ('" . $this->registry->user_id . "', '" . $this->registry->user_id .
            "', NOW(), NOW(), '" . $address . "', '" . $num . "'";
        foreach ($fields as $key => $value) {
            $nv = ($value == 'parent_id' && $nparent_id) ? $nparent_id:$page_data[$value];
            $query .= ", '" . $nv . "'";
        }
        $query .= ")";

        $this->dbh->exec($query);
        $ntdata_id = $this->dbh->lastInsertId();
        $query = "select * from csct_constants where data_type=2 and data_id=" . $tdata_id;
        $cResult = $this->dbh->queryFetchAll($query);
        if ($cResult) {
            foreach ($cResult as $cnstData)
                $this->dbh->exec("insert into csct_constants (data_id, data_type, ckey, cvalue) values ('" . $ntdata_id .
                    "', '2', '" . $cnstData['ckey'] . "', '" . $cnstData['cvalue'] . "')");
        }
        $lang_id = 0;
        $header = '';
        $title = '';
        $page_text = '';
        $page_preview = '';
        $seo_kw = '';
        $seo_descr = '';
        $query = "insert into csct_list_items_text values ('', '" . $ntdata_id .
            "', :lang_id, :header, :title, :page_preview, :page_text, :seo_kw, :seo_descr)";
        $sql = $this->dbh->prepare($query);
        $sql->bindParam(':lang_id', $lang_id, PDO::PARAM_INT);
        $sql->bindParam(':header', $header, PDO::PARAM_STR);
        $sql->bindParam(':title', $title, PDO::PARAM_STR);
        $sql->bindParam(':page_text', $page_text, PDO::PARAM_STR);
        $sql->bindParam(':page_preview', $page_preview, PDO::PARAM_STR);
        $sql->bindParam(':seo_kw', $seo_kw, PDO::PARAM_STR);
        $sql->bindParam(':seo_descr', $seo_descr, PDO::PARAM_STR);
        foreach ($page_data['text'] as $lang_id => $text_data) {
            $header = (!$nparent_id ? 'Копия ':'') . $text_data['header'];
            $title = $text_data['title'];
            $page_text = $text_data['page_text'];
            $page_preview = $text_data['page_preview'];
            $seo_kw = $text_data['seo_kw'];
            $seo_descr = $text_data['seo_descr'];
            $sql->execute();
            $sql->closeCursor();
        }

        if ($page_data['subtemplate'] != 0)
            $this->copy_stmpl($tdata_id, $ntdata_id, 1);

        if ($tpage_data['dtmpl_id_lc'] != 0)
            $this->copy_dtmpl_data($tdata_id, $ntdata_id, $tpage_data['dtmpl_id_lc']);

        return $ntdata_id;

    }

    /**
     * model_cmain::copy_stmpl()
     * 
     * @param mixed $tdata_id
     * @param mixed $ntdata_id
     * @param mixed $page_type
     * @return
     */
    private function copy_stmpl($tdata_id, $ntdata_id, $page_type)
    {
        $query = "select * from csct_stmpl_data_text where page_id=" . $tdata_id . " and page_type=" . $page_type;
        $result = $this->dbh->queryFetchAll($query);
        $block_id = 0;
        $lang_id = 0;
        $div_text = '';
        $query = "insert into csct_stmpl_data_text values ('', '" . $ntdata_id . "', '" . $page_type .
            "', :block_id, :lang_id, :div_text)";
        $sql = $this->dbh->prepare($query);
        $sql->bindParam(':block_id', $block_id, PDO::PARAM_INT);
        $sql->bindParam(':lang_id', $lang_id, PDO::PARAM_INT);
        $sql->bindParam(':div_text', $div_text, PDO::PARAM_STR);
        foreach ($result as $item) {
            $block_id = $item['block_id'];
            $lang_id = $item['lang_id'];
            $div_text = $item['div_text'];
            $sql->execute();
            $sql->closeCursor();
        }
    }

    /**
     * model_cmain::copy_dtmpl_data()
     * 
     * @param mixed $tdata_id
     * @param mixed $ntdata_id
     * @param mixed $dtmpl_id
     * @return
     */
    private function copy_dtmpl_data($tdata_id, $ntdata_id, $dtmpl_id)
    {
        $query = "select * from csct_tdata_fields where data_id=" . $tdata_id .
            " and field_id in (select id from csct_dtmpl_fields where dtmpl_id=" . $dtmpl_id . ")";
        $result = $this->dbh->queryFetchAll($query);
        $field_id = 0;
        $fvalue = '';
        $fnvalue = '';
        $fdvalue = '';
        $lang_id = 0;
        $query = "insert into csct_tdata_fields values ('', '" . $ntdata_id .
            "', :field_id, :lang_id, :fvalue, :fnvalue, :fdvalue)";
        $fisql = $this->dbh->prepare($query);
        $fisql->bindParam(':fvalue', $fvalue, PDO::PARAM_STR);
        $fisql->bindParam(':fdvalue', $fdvalue, PDO::PARAM_STR);
        $fisql->bindParam(':fnvalue', $fnvalue, PDO::PARAM_INT);
        $fisql->bindParam(':field_id', $field_id, PDO::PARAM_INT);
        $fisql->bindParam(':lang_id', $lang_id, PDO::PARAM_INT);
        foreach ($result as $item) {
            $fvalue = $item['fvalue'];
            $fdvalue = $item['fdvalue'];
            $fnvalue = $item['fnvalue'];
            $field_id = $item['field_id'];
            $lang_id = $item['lang_id'];
            $fisql->execute();
            $fisql->closeCursor();
        }
        $query = "select * from csct_tdata_flib where data_id=" . $tdata_id .
            " and flib_id in (select id from csct_dtmpl_fields where dtmpl_id=" . $dtmpl_id . ")";
        $result = $this->dbh->queryFetchAll($query);
        $field_id = 0;
        $lib_id = 0;
        $item_id = 0;
        $num = 1;
        $query = "insert into csct_tdata_flib values ('', :num, '" . $ntdata_id .
            "', :field_id, :lib_id, :item_id)";
        $fsql = $this->dbh->prepare($query);
        $fsql->bindParam(':num', $num, PDO::PARAM_INT);
        $fsql->bindParam(':field_id', $field_id, PDO::PARAM_INT);
        $fsql->bindParam(':lib_id', $lib_id, PDO::PARAM_INT);
        $fsql->bindParam(':item_id', $item_id, PDO::PARAM_INT);
        foreach ($result as $item) {
            $field_id = $item['flib_id'];
            $num = $item['num'];
            $lib_id = $item['lib_id'];
            $item_id = $item['item_id'];
            $fsql->execute();
            $fsql->closeCursor();
        }

        $query = "select * from csct_dp_links where data_id=" . $tdata_id .
            " and field_id in (select id from csct_dtmpl_fields where dtmpl_id=" . $dtmpl_id . ")";
        $result = $this->dbh->queryFetchAll($query);
        $field_id = 0;
        $lib_id = 0;
        $item_id = 0;
        $ltype = 0;
        $query = "insert into csct_dp_links values ('', '" . $ntdata_id . "', :field_id, :ltype, :item_id)";
        $plsql = $this->dbh->prepare($query);
        $plsql->bindParam(':field_id', $field_id, PDO::PARAM_INT);
        $plsql->bindParam(':item_id', $item_id, PDO::PARAM_INT);
        $plsql->bindParam(':ltype', $ltype, PDO::PARAM_INT);
        foreach ($result as $item) {
            $field_id = $item['field_id'];
            $item_id = $item['item_id'];
            $ltype = $item['ltype'];
            $plsql->execute();
            $plsql->closeCursor();
        }

        $query = "select * from csct_ds_links where data_id=" . $tdata_id;
        $result = $this->dbh->queryFetchAll($query);
        $field_id = 0;

        $sites_query = "insert into csct_ds_links (data_id, field_id, site_id) values ('" . $ntdata_id .
            "', :field_id, :site_id)";
        $insertSitesSql = $this->dbh->prepare($sites_query);
        $siteId = 0;
        $insertSitesSql->bindParam(':field_id', $field_id, PDO::PARAM_INT);
        $insertSitesSql->bindParam(':site_id', $siteId, PDO::PARAM_INT);
        foreach ($result as $item) {
            $field_id = $item['field_id'];
            $siteId = $item['item_id'];
            $insertSitesSql->execute();
            $insertSitesSql->closeCursor();
        }

        //группы

        $query = "select * from csct_tdata_groups where data_id=" . $tdata_id . " and dtmpl_id=" . $dtmpl_id;
        $tDataGroups = $this->dbh->queryFetchAll($query);
        foreach ($tDataGroups as $tDataGroup) {
            $query = "insert into csct_tdata_groups (data_id, dtmpl_id, group_id, name) values ('" . $ntdata_id .
                "', '" . $dtmpl_id . "', '" . $tDataGroup['group_id'] . "', '" . $tDataGroup['name'] . "')";
            $this->dbh->exec($query);
            $ngroup_id = $this->dbh->lastInsertId();

            $query = "select * from csct_ds_links where group_id=" . $tDataGroup['id'] . " and data_id=" . $tdata_id;
            $result = $this->dbh->queryFetchAll($query);
            $field_id = 0;
            $siteId = 0;
            $sites_query = "insert into csct_dgs_links (data_id, field_id, group_id, site_id) values ('" . $ntdata_id .
                "', :field_id, '" . $ngroup_id . "', :site_id)";
            $insertGSitesSql = $this->dbh->prepare($sites_query);
            $insertGSitesSql->bindParam(':field_id', $field_id, PDO::PARAM_INT);
            $insertGSitesSql->bindParam(':site_id', $siteId, PDO::PARAM_INT);

            $query = "select * from csct_tgdata_fields where data_id=" . $tdata_id . " and group_id=" . $tDataGroup['id'] .
                " and field_id in (select id from csct_dtmpl_fields where dtmpl_id=" . $dtmpl_id . ")";
            $result = $this->dbh->queryFetchAll($query);
            $field_id = 0;
            $fvalue = '';
            $fnvalue = '';
            $fdvalue = '';
            $lang_id = 0;
            $query = "insert into csct_tgdata_fields values ('', '" . $ntdata_id .
                "', :group_id, :field_id, :lang_id, :fvalue, :fnvalue, :fdvalue)";
            $fisql = $this->dbh->prepare($query);
            $fisql->bindParam(':fvalue', $fvalue, PDO::PARAM_STR);
            $fisql->bindParam(':fdvalue', $fdvalue, PDO::PARAM_STR);
            $fisql->bindParam(':fnvalue', $fnvalue, PDO::PARAM_INT);
            $fisql->bindParam(':field_id', $field_id, PDO::PARAM_INT);
            $fisql->bindParam(':lang_id', $lang_id, PDO::PARAM_INT);
            $fisql->bindParam(':group_id', $ngroup_id, PDO::PARAM_INT);
            foreach ($result as $item) {
                $fvalue = $item['fvalue'];
                $fdvalue = $item['fdvalue'];
                $fnvalue = $item['fnvalue'];
                $field_id = $item['field_id'];
                $lang_id = $item['lang_id'];
                $fisql->execute();
                $fisql->closeCursor();
            }
            $query = "select * from csct_tgdata_flib where data_id=" . $tdata_id . " and group_id=" . $tDataGroup['id'] .
                " and flib_id in (select id from csct_dtmpl_fields where dtmpl_id=" . $dtmpl_id . ")";
            $result = $this->dbh->queryFetchAll($query);
            $field_id = 0;
            $lib_id = 0;
            $item_id = 0;
            $num = 1;
            $query = "insert into csct_tgdata_flib values ('', :num, '" . $ntdata_id . "', '" . $ngroup_id .
                "', :field_id, :lib_id, :item_id)";
            $fsql = $this->dbh->prepare($query);
            $fsql->bindParam(':num', $num, PDO::PARAM_INT);
            $fsql->bindParam(':field_id', $field_id, PDO::PARAM_INT);
            $fsql->bindParam(':lib_id', $lib_id, PDO::PARAM_INT);
            $fsql->bindParam(':item_id', $item_id, PDO::PARAM_INT);
            foreach ($result as $item) {
                $field_id = $item['flib_id'];
                $num = $item['num'];
                $lib_id = $item['lib_id'];
                $item_id = $item['item_id'];
                $fsql->execute();
                $fsql->closeCursor();
            }

            $query = "select * from csct_dgp_links where data_id=" . $tdata_id . " and group_id=" . $tDataGroup['id'] .
                " and field_id in (select id from csct_dtmpl_fields where dtmpl_id=" . $dtmpl_id . ")";
            $result = $this->dbh->queryFetchAll($query);
            $field_id = 0;
            $lib_id = 0;
            $item_id = 0;
            $ltype = 0;
            $query = "insert into csct_dgp_links values ('', '" . $ntdata_id . "', '" . $ngroup_id .
                "', :field_id, :ltype, :item_id)";
            $plsql = $this->dbh->prepare($query);
            $plsql->bindParam(':field_id', $field_id, PDO::PARAM_INT);
            $plsql->bindParam(':item_id', $item_id, PDO::PARAM_INT);
            $plsql->bindParam(':ltype', $ltype, PDO::PARAM_INT);
            foreach ($result as $item) {
                $field_id = $item['field_id'];
                $item_id = $item['item_id'];
                $ltype = $item['ltype'];
                $plsql->execute();
                $plsql->closeCursor();
            }
        }
    }

    /**
     * model_cmain::block_license()
     * 
     * @param mixed $cstring
     * @return
     */
    function block_license($cstring)
    {
        $query = "update csct_settings set license='" . $cstring . "'";
        $this->dbh->exec($query);
    }

    /**
     * model_cmain::get_all_dtmpl_links()
     * 
     * @param mixed $tdata_id
     * @param mixed $dtmpl_id
     * @return
     */
    function get_all_dtmpl_links($tdata_id, $dtmpl_id)
    {
        $query = "select mt.id, mt.item_id, nt.name from csct_tdata_flib mt, csct_lib_content_names nt where mt.data_id=" .
            $tdata_id . " and mt.item_id=nt.data_id and mt.flib_id in (select field_id from csct_dtmpl_flib where template_id=" .
            $dtmpl_id . ")";
        return $this->dbh->queryFetchAll($query);
    }

    function del_page($tdata_id)
    {
        $this->trigger_execute('beforePageDelete', $tdata_id);
        $page_data = $this->get_page_data($tdata_id, false);

        //если лента, удаляем элементы
        if ($page_data['db_type'] == 1) {
            $query = "delete from csct_list_items_text where data_id in (select id from csct_list_items where parent_id=" .
                $tdata_id . ")";
            $this->dbh->exec($query);
            $query = "delete from csct_pics_text where data_id in (select id from csct_pics where data_type=2 and data_id in (select id from csct_list_items where parent_id=" .
                $tdata_id . "))";
            $this->dbh->exec($query);
            $query = "delete from csct_pics where data_type=2 and data_id in (select id from csct_list_items where parent_id=" .
                $tdata_id . ")";
            $this->dbh->exec($query);
            if ($page_data['dtmpl_id_lc']) {
                $query = "delete from csct_tdata_fields where data_id in (select id from csct_list_items where parent_id=" .
                    $tdata_id . ") and field_id in (select id from csct_dtmpl_fields where dtmpl_id=" . $page_data['dtmpl_id_lc'] .
                    ")";
                $this->dbh->exec($query);
                $query = "delete from csct_tdata_flib where data_id in (select id from csct_list_items where parent_id=" .
                    $tdata_id . ") and flib_id in (select id from csct_dtmpl_fields where dtmpl_id=" . $page_data['dtmpl_id_lc'] .
                    ")";
                $this->dbh->exec($query);
                $query = "delete from csct_dp_links where data_id in (select id from csct_list_items where parent_id=" .
                    $tdata_id . ") and flib_id in (select id from csct_dtmpl_fields where dtmpl_id=" . $page_data['dtmpl_id_lc'] .
                    ")";
                $this->dbh->exec($query);

                $query = "delete from csct_tgdata_fields where data_id in (select id from csct_list_items where parent_id=" .
                    $tdata_id . ") and field_id in (select id from csct_dtmpl_fields where dtmpl_id=" . $page_data['dtmpl_id_lc'] .
                    ")";
                $this->dbh->exec($query);
                $query = "delete from csct_tgdata_flib where data_id in (select id from csct_list_items where parent_id=" .
                    $tdata_id . ") and flib_id in (select id from csct_dtmpl_fields where dtmpl_id=" . $page_data['dtmpl_id_lc'] .
                    ")";
                $this->dbh->exec($query);
                $query = "delete from csct_dgp_links where data_id in (select id from csct_list_items where parent_id=" .
                    $tdata_id . ") and flib_id in (select id from csct_dtmpl_fields where dtmpl_id=" . $page_data['dtmpl_id_lc'] .
                    ")";
                $this->dbh->exec($query);
            }
            $query = "delete from csct_stmpl_data_text where page_id in (select id from csct_list_items where parent_id=" .
                $tdata_id . ") and page_type=1";
            $this->dbh->exec($query);
            $query = "delete from csct_list_items where parent_id=" . $tdata_id;
            $this->dbh->exec($query);
        }
        $query = "delete from csct_pages_text where data_id =" . $tdata_id;
        $this->dbh->exec($query);

        $query = "delete from csct_pics_text where data_id in (select id from csct_pics where data_type=1 and data_id=" .
            $tdata_id . ")";
        $this->dbh->exec($query);
        $query = "delete from csct_pics where data_type=1 and data_id=" . $tdata_id;
        $this->dbh->exec($query);
        if ($page_data['dtmpl_id']) {
            $query = "delete from csct_tdata_fields where data_id=" . $tdata_id .
                " and field_id in (select id from csct_dtmpl_fields where dtmpl_id=" . $page_data['dtmpl_id'] . ")";
            $this->dbh->exec($query);
            $query = "delete from csct_tdata_flib where data_id=" . $tdata_id .
                " and flib_id in (select id from csct_dtmpl_fields where dtmpl_id=" . $page_data['dtmpl_id'] . ")";
            $this->dbh->exec($query);
            $query = "delete from csct_dp_links where data_id=" . $tdata_id .
                " and flib_id in (select id from csct_dtmpl_fields where dtmpl_id=" . $page_data['dtmpl_id'] . ")";
            $this->dbh->exec($query);

            $query = "delete from csct_tgdata_fields where data_id=" . $tdata_id .
                " and field_id in (select id from csct_dtmpl_fields where dtmpl_id=" . $page_data['dtmpl_id'] . ")";
            $this->dbh->exec($query);
            $query = "delete from csct_tgdata_flib where data_id=" . $tdata_id .
                " and flib_id in (select id from csct_dtmpl_fields where dtmpl_id=" . $page_data['dtmpl_id'] . ")";
            $this->dbh->exec($query);
            $query = "delete from csct_dgp_links where data_id=" . $tdata_id .
                " and flib_id in (select id from csct_dtmpl_fields where dtmpl_id=" . $page_data['dtmpl_id'] . ")";
            $this->dbh->exec($query);
        }
        $query = "delete from csct_stmpl_data_text where page_id=" . $tdata_id . " and page_type=0";
        $this->dbh->exec($query);

        //перемещаем дочерние страницы
        $query = "update csct_pages set parent=" . $page_data['parent'] . " where parent=" . $tdata_id;
        $this->dbh->exec($query);

        //группы и привязки
        $query = "update csct_page_groups set parent_page=" . $page_data['parent'] . " where parent_page=" .
            $tdata_id;
        $this->dbh->exec($query);
        $query = "delete from csct_pg_link where data_id=" . $tdata_id;
        $this->dbh->exec($query);

        $query = "delete from csct_pages where id=" . $tdata_id;
        $this->dbh->exec($query);
        $this->trigger_execute('afterPageDelete', $tdata_id);
    }

    function del_pgr($tdata_id)
    {
        $this->trigger_execute('beforePageGroupDelete', $tdata_id);
        $pgr_data = $this->get_page_group($tdata_id);
        if ($pgr_data['page_data']['dtmpl_id']) {
            $query = "delete from csct_tdata_fields where data_id=" . $tdata_id .
                " and field_id in (select id from csct_dtmpl_fields where dtmpl_id=" . $pgr_data['page_data']['dtmpl_id'] .
                ")";
            $this->dbh->exec($query);
            $query = "delete from csct_tdata_flib where data_id=" . $tdata_id .
                " and flib_id in (select id from csct_dtmpl_fields where dtmpl_id=" . $pgr_data['page_data']['dtmpl_id'] .
                ")";
            $this->dbh->exec($query);
            $query = "delete from csct_dp_links where data_id=" . $tdata_id .
                " and flib_id in (select id from csct_dtmpl_fields where dtmpl_id=" . $pgr_data['page_data']['dtmpl_id'] .
                ")";
            $this->dbh->exec($query);

            $query = "delete from csct_tgdata_fields where data_id=" . $tdata_id .
                " and field_id in (select id from csct_dtmpl_fields where dtmpl_id=" . $pgr_data['page_data']['dtmpl_id'] .
                ")";
            $this->dbh->exec($query);
            $query = "delete from csct_tgdata_flib where data_id=" . $tdata_id .
                " and flib_id in (select id from csct_dtmpl_fields where dtmpl_id=" . $pgr_data['page_data']['dtmpl_id'] .
                ")";
            $this->dbh->exec($query);
            $query = "delete from csct_dgp_links where data_id=" . $tdata_id .
                " and flib_id in (select id from csct_dtmpl_fields where dtmpl_id=" . $pgr_data['page_data']['dtmpl_id'] .
                ")";
            $this->dbh->exec($query);
        }
        $query = "delete from csct_page_groups_names where data_id=" . $tdata_id;
        $this->dbh->exec($query);
        $query = "delete from csct_pg_link where pg_id=" . $tdata_id;
        $this->dbh->exec($query);
        $query = "delete from csct_pgr_link where data_id=" . $tdata_id;
        $this->dbh->exec($query);

        $query = "delete from csct_page_groups where id=" . $tdata_id;
        $this->dbh->exec($query);
        $this->trigger_execute('afterPageGroupDelete', $tdata_id);

    }

    function get_pp_dtmpl($dtmpl_id, &$conditions)
    {
        $dmodel = app()->load_model('common' . DIRSEP . 'dtemplates', 'model_dtemplates');
        $dtmpl = $dmodel->get_dtmpl_structure($dtmpl_id);
        foreach ($dtmpl['fields'] as $field) {
            if ($field['ftype'] == 0 && $_POST['field_' . $field['id']])
                $conditions[] = "mt.id in (select data_id from csct_tdata_fields where field_id=" . $field['id'] .
                    " and fvalue like '%" . $_POST['field_' . $field['id']] . "%')";
            elseif ($field['ftype'] == 3 && $_POST['field_' . $field['id']])
                $conditions[] = "mt.id in (select data_id from csct_tdata_fields where field_id=" . $field['id'] .
                    " and fnvalue " . $_POST['cfield_' . $field['id']] . " '" . $_POST['field_' . $field['id']] . "')";
            elseif ($field['ftype'] == 7 && $_POST['field_' . $field['id']]) {
                list($d, $m, $y) = explode(".", $_POST['field_' . $field['id']]);
                $fvalue = $y . '-' . $m . '-' . $d;
                $conditions[] = "mt.id in (select data_id from csct_tdata_fields where field_id=" . $field['id'] .
                    " and fdvalue = '" . $fvalue . "')";
            }
            elseif ($field['ftype'] == 4 && $_POST['field_' . $field['id']] != -1) {
                $conditions[] = "mt.id in (select data_id from csct_tdata_fields where field_id=" . $field['id'] .
                    " and fnvalue = '" . $_POST['field_' . $field['id']] . "')";
            }
            elseif ($field['ftype'] == 5 && $_POST['field_' . $field['id']] != -1) {
                $conditions[] = "mt.id in (select data_id from csct_tdata_fields where field_id=" . $field['id'] .
                    " and fnvalue = '" . $_POST['field_' . $field['id']] . "')";
            }
            elseif (($field['ftype'] == 2 || $field['ftype'] == 6) && $_POST['field_' . $field['id']] && $_POST['field_' .
                $field['id']][0]) {
                $conditions[] = "mt.id in (select data_id from csct_tdata_flib where flib_id=" . $field['id'] .
                    " and item_id in (" . join(",", $_POST['field_' . $field['id']]) . "))";
            }
        }
    }

    function get_pp_li()
    {
        $query = "select mt.id, nt.header from csct_list_items mt, csct_list_items_text nt";
        $conditions = array();
        $conditions[] = "mt.id=nt.data_id";
        $conditions[] = "mt.parent_id=" . $_POST['list'];
        if ($_POST['header'])
            $conditions[] = "nt.header like '%" . $_POST['header'] . "%'";
        if ($_POST['address'])
            $conditions[] = "mt.address like '%" . $_POST['address'] . "%'";
        if ($_POST['photo'] != -1)
            $conditions[] = "mt.use_photo=" . $_POST['photo'];
        if ($_POST['archive'] != -1)
            $conditions[] = "mt.archive=" . $_POST['archive'];
        if (isset($_POST['status']) && $_POST['status'] != -1)
            $conditions[] = "mt.status=" . $_POST['status'];
        if (isset($_POST['wdtmpl']))
            $this->get_pp_dtmpl($_POST['dtmpl_id'], $conditions);
        $query .= " where " . join(" and ", $conditions);
        return $this->dbh->queryFetchAll($query);
    }

    function get_pp_pages()
    {
        $query = "select mt.id, nt.header from csct_pages mt, csct_pages_text nt";
        $conditions = array();
        $conditions[] = "mt.id=nt.data_id";
        if ($_POST['header'])
            $conditions[] = "nt.header like '%" . $_POST['header'] . "%'";
        if ($_POST['address'])
            $conditions[] = "mt.address like '%" . $_POST['address'] . "%'";
        if ($_POST['photo'] != -1)
            $conditions[] = "mt.use_photo=" . $_POST['photo'];
        if ($_POST['archive'] != -1)
            $conditions[] = "mt.archive=" . $_POST['archive'];
        if ($_POST['service'] != -1)
            $conditions[] = "mt.service=" . $_POST['service'];
        if (isset($_POST['ml']) && $_POST['ml'] != -1)
            $conditions[] = "mt.use_ml=" . $_POST['ml'];
        if (isset($_POST['md']) && $_POST['md'] != -1)
            $conditions[] = "mt.use_md=" . $_POST['md'];
        if (isset($_POST['site_id']) && $_POST['site_id'])
            $conditions[] = "mt.id in (select data_id from csct_site_links where data_type=0 and site_id in (" .
                join(",", $_POST['site_id']) . "))";
        if ($_POST['template'] != -1)
            $conditions[] = "mt.template=" . $_POST['template'];
        if ($_POST['dtmpl_id'] != -1)
            $conditions[] = "mt.dtmpl_id=" . $_POST['dtmpl_id'];
        if (isset($_POST['pgl']) && $_POST['pgl'] != -1) {
            if ($_POST['pgl'] != 0)
                $conditions[] = "mt.id in (select data_id from csct_pg_link where pg_id in (" . join(",", $_POST['pgl']) .
                    "))";
            else
                $conditions[] = "mt.id not in (select data_id from csct_pg_link)";
        }
        if ($_POST['parent'])
            $conditions[] = "mt.parent=" . $_POST['parent'];
        if (isset($_POST['status']) && $_POST['status'] != -1)
            $conditions[] = "mt.status=" . $_POST['status'];
        if (isset($_POST['wdtmpl']))
            $this->get_pp_dtmpl($_POST['dtmpl_id'], $conditions);
        $query .= " where " . join(" and ", $conditions) . " group by mt.id";
        return $this->dbh->queryFetchAll($query);
    }

    function get_pp_pgr()
    {
        $query = "select mt.id, nt.name header from csct_page_groups mt, csct_page_groups_names nt";
        $conditions = array();
        $conditions[] = "mt.id=nt.data_id";
        if ($_POST['header'])
            $conditions[] = "nt.name like '%" . $_POST['header'] . "%'";

        if (isset($_POST['ml']) && $_POST['ml'] != -1)
            $conditions[] = "mt.use_ml=" . $_POST['ml'];
        if (isset($_POST['md']) && $_POST['md'] != -1)
            $conditions[] = "mt.use_md=" . $_POST['md'];
        if (isset($_POST['site_id']) && $_POST['site_id'])
            $conditions[] = "mt.id in (select data_id from csct_site_links where data_type=0 and site_id in (" .
                join(",", $_POST['site_id']) . "))";

        if ($_POST['dtmpl_id'] != -1)
            $conditions[] = "mt.dtmpl_id=" . $_POST['dtmpl_id'];
        if ($_POST['parent'])
            $conditions[] = "mt.parent_page=" . $_POST['parent'];
        if (isset($_POST['pgl']) && $_POST['pgl'] != -1) {
            if ($_POST['pgl'] != 0)
                $conditions[] = "mt.id in (select data_id from csct_pgr_link where pg_id in (" . join(",", $_POST['pgl']) .
                    "))";
            else
                $conditions[] = "mt.id not in (select data_id from csct_pgr_link)";
        }
        if (isset($_POST['status']) && $_POST['status'] != -1)
            $conditions[] = "mt.status=" . $_POST['status'];
        if (isset($_POST['wdtmpl']))
            $this->get_pp_dtmpl($_POST['dtmpl_id'], $conditions);
        $query .= " where " . join(" and ", $conditions);
        return $this->dbh->queryFetchAll($query);
    }

    function get_pp_lib()
    {
        $query = "select mt.id, nt.name header from csct_lib_content mt, csct_lib_content_names nt";
        $conditions = array();
        $conditions[] = "mt.id=nt.data_id";
        $conditions[] = "mt.ref_id=" . $_POST['lib'];
        if ($_POST['header'])
            $conditions[] = "nt.name like '%" . $_POST['header'] . "%'";
        if ($_POST['dtmpl_id'] != -1)
            $conditions[] = "mt.ref_id in (select id from csct_library where dtmpl_id=" . $_POST['dtmpl_id'] .
                ")";
        if (isset($_POST['wdtmpl']))
            $this->get_pp_dtmpl($_POST['dtmpl_id'], $conditions);
        if (isset($_POST['status']) && $_POST['status'] != -1)
            $conditions[] = "mt.status=" . $_POST['status'];
        $query .= " where " . join(" and ", $conditions);
        return $this->dbh->queryFetchAll($query);
    }

    function pp_upd_tfield()
    {
        $tables = array(
            '1' => 'csct_pages_text',
            '2' => 'csct_lib_content_names',
            '3' => 'csct_list_items_text',
            '4' => 'csct_page_groups_names');
        $lid = isset($_POST['lid']) ? $_POST['lid']:0;
        $query = "update `" . $tables[$_POST['et']] . "` set `" . $_POST['field'] .
            "`=:value where data_id=" . $_POST['pk'] . " and lang_id=" . $lid;
        $sql = $this->dbh->prepare($query);
        $sql->bindParam(':value', $_POST['value'], PDO::PARAM_STR);
        $sql->execute();
        $sql->closeCursor();
    }

    function pp_upd_field()
    {
        $tables = array(
            '1' => 'csct_pages',
            '2' => 'csct_lib_content',
            '3' => 'csct_list_items',
            '4' => 'csct_page_groups');
        $query = "update `" . $tables[$_POST['et']] . "` set `" . $_POST['field'] . "`=:value where id=" . $_POST['pk'];
        $sql = $this->dbh->prepare($query);
        $value = isset($_POST['value']) ? $_POST['value']:0;
        if (is_array($value))
            $value = $value[0];
        $sql->bindParam(':value', $value);
        $sql->execute();
        $sql->closeCursor();
    }

    function pp_upd_ousers()
    {
        $ptype = $_POST['et'] == 1 ? 0:1;
        $this->dbh->exec('delete from csct_userlinks where data_type=' . $ptype . ' and data_id=' . $_POST['pk']);
        if ($_POST['value'])
            foreach ($_POST['value'] as $user)
                $this->dbh->exec("insert into csct_userlinks values ('', '" . $_POST['pk'] . "', '" . $ptype .
                    "', '" . $user . "')");
    }

    function pp_upd_dsites()
    {
        $query = "delete from csct_ds_links where field_id=" . $_POST['field_id'] . " and data_id=" . $_POST['pk'];
        $this->dbh->query($query);
        $siteId = 0;
        $sites_query = "insert into csct_ds_links (data_id, field_id, site_id) values ('" . $_POST['pk'] .
            "', :field_id, :site_id)";
        $insertSitesSql = $this->dbh->prepare($sites_query);
        $insertSitesSql->bindParam(':field_id', $_POST['field_id'], PDO::PARAM_INT);
        $insertSitesSql->bindParam(':site_id', $siteId, PDO::PARAM_INT);
        foreach ($_POST['value'] as $siteId) {
            $insertSitesSql->execute();
            $insertSitesSql->closeCursor();
        }
    }

    function pp_upd_sites()
    {
        $type = $_POST['et'] == 1 ? 0:1;

        if (app()->md && isset($_POST['use_md']) && $_POST['use_md'] && isset($_POST['value'])) {
            $squery = $this->get_sites($_POST['pk']);
            $site_id = 0;
            $sq = "select id from csct_site_links where data_type=" . $type . " and data_id=" . $_POST['pk'] .
                " and site_id=:site_id";
            $ssql = $this->dbh->prepare($sq);
            $ssql->bindParam(':site_id', $site_id, PDO::PARAM_INT);
            foreach ($squery['sites'] as $site) {
                if (in_array($site['id'], $_POST['value'])) {
                    $site_id = $site['id'];
                    $ssql->execute();
                    $rsd = $ssql->fetch();
                    $ssql->closeCursor();
                    if (!$rsd)
                        $this->dbh->exec("insert into csct_site_links (data_id, data_type, site_id) values ('" . $_POST['pk'] .
                            "', '" . $type . "', '" . $site_id . "')");
                }
                else
                    $this->dbh->exec("delete from csct_site_links where data_type=" . $type . " and data_id=" . $_POST['pk'] .
                        " and site_id=" . $site['id']);
            }
        }
        else
            $this->dbh->exec("delete from csct_site_links where data_type=0 and data_id=" . $_POST['pk']);
    }

    function pp_upd_groups()
    {
        $table = $_REQUEST['et'] == 1 ? 'csct_pg_link':'csct_pgr_link';
        $pg_id = 0;
        $num = 1;
        $query = "insert into " . $table . " values ('', '" . $_POST['pk'] . "', :pg_id, :num)";
        $pg_sql = $this->dbh->prepare($query);
        $pg_sql->bindParam(':pg_id', $pg_id, PDO::PARAM_INT);
        $pg_sql->bindParam(':num', $num, PDO::PARAM_INT);

        $query = "delete from " . $table . " where data_id=" . $_POST['pk'] . " and pg_id=:pg_id";
        $pg_dsql = $this->dbh->prepare($query);
        $pg_dsql->bindParam(':pg_id', $pg_id, PDO::PARAM_INT);
        $query = "select max(num) from " . $table . " where pg_id=:pg_id";
        $pg_nsql = $this->dbh->prepare($query);
        $pg_nsql->bindParam(':pg_id', $pg_id, PDO::PARAM_INT);
        $query = "select pg_id from " . $table . " where data_id=" . $_POST['pk'];
        $exist_pgl = $this->dbh->query($query)->fetchAll(PDO::FETCH_COLUMN);
        if ($exist_pgl) {
            foreach ($exist_pgl as $key => $val) {
                if (isset($_POST['value']) && $_POST['value']) {
                    foreach ($_POST['value'] as $nkey => $nval) {
                        if ($val == $nval) {
                            unset($_POST['value'][$nkey]);
                            unset($exist_pgl[$key]);
                            break;
                        }
                    }
                }
            }
        }

        //новые
        if (isset($_POST['value']) && $_POST['value']) {
            foreach ($_POST['value'] as $pg_id) {
                $pg_nsql->execute();
                $nr = $pg_nsql->fetch();
                $num = $nr ? current($pg_nsql) + 1:1;
                $pg_nsql->closeCursor();
                $pg_sql->execute();
                $pg_sql->closeCursor();
            }
        }
        //удаляем старые
        if ($exist_pgl) {
            foreach ($exist_pgl as $pg_id) {
                $pg_dsql->execute();
                $pg_dsql->closeCursor();
            }
        }
    }

    function pp_get_li_prv($li_id, $lid)
    {
        $query = "select use_ml from csct_pages where id=(select parent_id from csct_list_items where id=" .
            $li_id . ")";
        $use_ml = current($this->dbh->queryFetchRow($query));
        if (!$use_ml && $lid)
            $lid = 0;
        elseif ($use_ml && !$lid)
            $lid = app()->lang_main;
        $query = "select page_preview from csct_list_items_text where data_id=" . $li_id . " and lang_id=" .
            $lid;
        return current($this->dbh->queryFetchRow($query));
    }

    function pp_get_li_text($tdata_id, $lid, $et)
    {
        $table = $et == 1 ? 'csct_pages_text':'csct_list_items_text';
        if ($et != 1)
            $query = "select use_ml from csct_pages where id=(select parent_id from csct_list_items where id=" .
                $tdata_id . ")";
        else
            $query = "select use_ml from csct_pages where id=(" . $tdata_id;
        $use_ml = current($this->dbh->queryFetchRow($query));
        if (!$use_ml && $lid)
            $lid = 0;
        elseif ($use_ml && !$lid)
            $lid = app()->lang_main;
        $query = "select page_text from " . $table . " where data_id=" . $tdata_id . " and lang_id=" . $lid;
        return current($this->dbh->queryFetchRow($query));
    }

    function pp_upd_dfield()
    {
        $ftype = isset($_REQUEST['ftype']) ? $_REQUEST['ftype']:0;
        if (in_array($ftype, array(
            0,
            1,
            3,
            4,
            5,
            7,
            9))) {
            $lang_id = isset($_REQUEST['lid']) ? $_REQUEST['lid']:0;

            if ($ftype == 7) {
                list($d, $m, $y) = explode(".", $_REQUEST['value']);
                $field_value = $y . '-' . $m . '-' . $d;
            }
            else {
                $field_value = isset($_REQUEST['value']) ? $_REQUEST['value']:0;
                if (is_array($field_value))
                    $field_value = $field_value[0];
            }
            $fld_id = isset($_REQUEST['fld_id']) ? $_REQUEST['fld_id']:null;
            $this->upd_td_fields($_REQUEST['pk'], $_REQUEST['field'], $ftype, $field_value, $lang_id, $fld_id);
        }
        elseif ($ftype == 8) {
            $this->dbh->exec("delete from csct_dp_links where field_id=" . $_REQUEST['field'] . " and data_id=" .
                $_REQUEST['pk']);
            if (isset($_REQUEST['value']) && $_REQUEST['value'] && $_REQUEST['value'][0]) {
                foreach ($_REQUEST['value'] as $item) {
                    //list($ltype, $item_id) = explode("_", $item);
                    $ltype = substr($item, -1);
                    $item_id = substr($item, 0, -1);
                    $this->dbh->exec("insert into csct_dp_links values ('', '" . $_REQUEST['pk'] . "', '" . $_REQUEST['field'] .
                        "', '" . $ltype . "', '" . $item_id . "')");
                }
            }
        }
        elseif ($ftype == 2 || $ftype == 6) {
            $item_id = 0;
            $num = 1;
            $lib_id = 0;
            $query = "insert into csct_tdata_flib values ('', :num, '" . $_REQUEST['pk'] . "', '" . $_REQUEST['field'] .
                "', '0', :item_id)";
            $pg_sql = $this->dbh->prepare($query);
            $pg_sql->bindParam(':item_id', $item_id, PDO::PARAM_INT);
            $pg_sql->bindParam(':num', $num, PDO::PARAM_INT);
            $query = "delete from csct_tdata_flib where data_id=" . $_REQUEST['pk'] .
                " and item_id=:item_id and flib_id=" . $_REQUEST['field'];
            $pg_dsql = $this->dbh->prepare($query);
            $pg_dsql->bindParam(':item_id', $item_id, PDO::PARAM_INT);
            $query = "select max(num) from csct_tdata_flib where data_id=" . $_REQUEST['pk'] . " and flib_id=" .
                $_REQUEST['field'];
            $pg_nsql = $this->dbh->prepare($query);
            $query = "select item_id from csct_tdata_flib where data_id=" . $_POST['pk'] . " and flib_id=" . $_REQUEST['field'];
            $exist_pgl = $this->dbh->query($query)->fetchAll(PDO::FETCH_COLUMN);
            if ($exist_pgl) {
                foreach ($exist_pgl as $key => $val) {
                    if (isset($_POST['value']) && $_POST['value']) {
                        foreach ($_POST['value'] as $nkey => $nval) {
                            if ($val == $nval) {
                                unset($_POST['value'][$nkey]);
                                unset($exist_pgl[$key]);
                                break;
                            }
                        }
                    }
                }
            }

            //новые
            if (isset($_POST['value']) && $_POST['value']) {
                foreach ($_POST['value'] as $item_id) {
                    $pg_nsql->execute();
                    $nr = $pg_nsql->fetch();
                    $num = $nr ? current($pg_nsql) + 1:1;
                    $pg_nsql->closeCursor();
                    $pg_sql->execute();
                    $pg_sql->closeCursor();
                }
            }
            //удаляем старые
            if ($exist_pgl) {
                foreach ($exist_pgl as $item_id) {
                    $pg_dsql->execute();
                    $pg_dsql->closeCursor();
                }
            }
        }
    }

    function pp_transfer()
    {

        if ($_POST['et'] == 1) {
            $item = $this->get_page_data($_POST['source'], true, false, false, true, true);
            $itemData = $item['page_data'];
            if (isset($_POST['dtmpl_id']))
                $item['dtmpl_data'] = $this->get_dtmpl_data($item['page_data']['id'], 1, $_POST['dtmpl_id']);
        }
        elseif ($_POST['et'] == 2) {
            $rslt = $this->msa(1, 1000000, null, $_POST['source']);
            $item = $rslt['list'][0];
            $itemData = $item;
            if (isset($_POST['dtmpl_id']))
                $item['dtmpl_data'] = $this->get_dtmpl_data($item['id'], 2, $_POST['dtmpl_id']);
        }
        elseif ($_POST['et'] == 3) {
            $item = $this->get_lpage_data($_POST['source']);
            $itemData = $item['page_data'];
            if (isset($_POST['dtmpl_id']))
                $item['dtmpl_data'] = $this->get_dtmpl_data($item['page_data']['id'], 3, $_POST['dtmpl_id']);
        }
        elseif ($_POST['et'] == 4) {
            $item = $this->get_page_group($_POST['source'], false);
            $itemData = $item['page_data'];
            if (isset($_POST['dtmpl_id']))
                $item['dtmpl_data'] = $this->get_dtmpl_data($item['id'], 4, $_POST['dtmpl_id']);
        }

        //общая инфа
        $tables = array(
            '1' => 'csct_pages',
            '2' => 'csct_lib_content',
            '3' => 'csct_list_items',
            '4' => 'csct_page_groups');
        $sets = array();
        if (isset($_REQUEST['address']))
            $sets[] = "address=:address";
        if (isset($_REQUEST['ptype']))
            $sets[] = "db_type=:db_type";
        if (isset($_REQUEST['plink']))
            $sets[] = "plink=:plink";
        if (isset($_REQUEST['file_link']))
            $sets[] = "file_link=:file_link";
        if (isset($_REQUEST['ltype']))
            $sets[] = "ltype=:ltype";
        if (isset($_REQUEST['template']))
            $sets[] = "template=:template";
        if (isset($_REQUEST['subtemplate']))
            $sets[] = "subtemplate=:subtemplate";
        if (isset($_REQUEST['dtmpl']))
            $sets[] = "dtmpl_id=:dtmpl_id";
        if (isset($_REQUEST['lib_id']))
            $sets[] = "lib_id=:lib_id";
        if (isset($_REQUEST['dtmpl_lc']))
            $sets[] = "dtmpl_id_lc=:dtmpl_id_lc";
        if (isset($_REQUEST['page_snp']))
            $sets[] = "page_snp=:page_snp";
        if (isset($_REQUEST['snp_list']))
            $sets[] = "snp_list=:snp_list";
        if (isset($_REQUEST['snp_list_item']))
            $sets[] = "snp_list_item=:snp_list_item";
        if (isset($_REQUEST['use_md']))
            $sets[] = "use_md=:use_md";
        if (isset($_REQUEST['use_ml']))
            $sets[] = "use_ml=:use_ml";
        if (isset($_REQUEST['archive']))
            $sets[] = "archive=:archive";
        if (isset($_REQUEST['service']))
            $sets[] = "service=:service";
        if (isset($_REQUEST['priority']))
            $sets[] = "priority=:priority";
        if (isset($_REQUEST['show_menu']))
            $sets[] = "show_menu=:show_menu";
        if (isset($_REQUEST['p_access']))
            $sets[] = "p_access=:p_access";
        if (isset($_REQUEST['use_photo']))
            $sets[] = "use_photo=:use_photo";
        if (isset($_REQUEST['status']))
            $sets[] = "status=:status";

        if (isset($_REQUEST['phg_resize'])) {
            $sets[] = "phg_width=:phg_width";
            $sets[] = "phg_height=:phg_height";
        }
        if (isset($_REQUEST['phg_mresize'])) {
            $sets[] = "phg_mwidth=:phg_mwidth";
            $sets[] = "phg_mheight=:phg_mheight";
        }
        if (isset($_REQUEST['phg_wm']))
            $sets[] = "phg_wm=:phg_wm";

        if (isset($_REQUEST['parent']))
            $sets[] = "parent=:parent";
        if (isset($_REQUEST['use_preview']))
            $sets[] = "use_preview=:use_preview";
        if (isset($_REQUEST['li_sort']))
            $sets[] = "sorting=:sorting";
        if ($sets) {
            $query = "update " . $tables[$_REQUEST['et']];
            $query .= " set " . join(", ", $sets);
            $query .= " where id in (" . join(",", $_REQUEST['dest']) . ")";
            $sql = $this->dbh->prepare($query);
            if (isset($_REQUEST['ptype']))
                $sql->bindParam(':db_type', $itemData['db_type']);
            if (isset($_REQUEST['plink']))
                $sql->bindParam(':plink', $itemData['plink']);
            if (isset($_REQUEST['ltype']))
                $sql->bindParam(':ltype', $itemData['ltype']);
            if (isset($_REQUEST['file_link']))
                $sql->bindParam(':file_link', $itemData['file_link']);
            if (isset($_REQUEST['address']))
                $sql->bindParam(':address', $itemData['address']);
            if (isset($_REQUEST['template']))
                $sql->bindParam(':template', $itemData['template']);
            if (isset($_REQUEST['lib_id']))
                $sql->bindParam(':lib_id', $itemData['lib_id']);
            if (isset($_REQUEST['subtemplate']))
                $sql->bindParam(':subtemplate', $itemData['subtemplate']);
            if (isset($_REQUEST['dtmpl']))
                $sql->bindParam(':dtmpl_id', $itemData['dtmpl_id']);
            if (isset($_REQUEST['dtmpl_lc']))
                $sql->bindParam(':dtmpl_id_lc', $itemData['dtmpl_id_lc']);
            if (isset($_REQUEST['page_snp']))
                $sql->bindParam(':page_snp', $itemData['page_snp']);
            if (isset($_REQUEST['snp_list']))
                $sql->bindParam(':snp_list', $itemData['snp_list']);
            if (isset($_REQUEST['snp_list_item']))
                $sql->bindParam(':snp_list_item', $itemData['snp_list_item']);
            if (isset($_REQUEST['use_md']))
                $sql->bindParam(':use_md', $itemData['use_md']);
            if (isset($_REQUEST['use_ml']))
                $sql->bindParam(':use_ml', $itemData['use_ml']);
            if (isset($_REQUEST['archive']))
                $sql->bindParam(':archive', $itemData['archive']);
            if (isset($_REQUEST['service']))
                $sql->bindParam(':service', $itemData['service']);
            if (isset($_REQUEST['priority']))
                $sql->bindParam(':priority', $itemData['priority']);
            if (isset($_REQUEST['show_menu']))
                $sql->bindParam(':show_menu', $itemData['show_menu']);
            if (isset($_REQUEST['p_access']))
                $sql->bindParam(':p_access', $itemData['p_access']);
            if (isset($_REQUEST['use_photo']))
                $sql->bindParam(':use_photo', $itemData['use_photo']);
            if (isset($_REQUEST['status']))
                $sql->bindParam(':status', $itemData['status']);
            if (isset($_REQUEST['phg_resize'])) {
                $sql->bindParam(':phg_width', $itemData['phg_width']);
                $sql->bindParam(':phg_height', $itemData['phg_height']);
            }
            if (isset($_REQUEST['phg_mresize'])) {
                $sql->bindParam(':phg_mwidth', $itemData['phg_mwidth']);
                $sql->bindParam(':phg_mheight', $itemData['phg_mheight']);
            }
            if (isset($_REQUEST['phg_wm']))
                $sql->bindParam(':phg_wm', $itemData['phg_wm']);

            if (isset($_REQUEST['parent']))
                $sql->bindParam(':parent', $itemData['parent']);
            if (isset($_REQUEST['use_preview']))
                $sql->bindParam(':use_preview', $itemData['use_preview']);
            if (isset($_REQUEST['li_sort']))
                $sql->bindParam(':sorting', $itemData['sorting']);
            $sql->execute();
            $sql->closeCursor();
        }
        //константы
        if (isset($_REQUEST['constants'])) {
            $query = "select * from csct_constants where data_type=" . ($_REQUEST['et'] == 1 ? "1":"2") .
                " and data_id=" . $_POST['source'];
            $cResult = $this->dbh->queryFetchAll($query);
            if ($cResult) {
                $key = 0;
                $value = '';
                $cnstIQuery = "insert into csct_constants (data_id, data_type, ckey, cvalue) values (:dstId, '" . ($_REQUEST['et'] ==
                    1 ? "1":"2") . "', :key, :value)";
                $cnstISql = $this->dbh->prepare($cnstIQuery);
                $cnstISql->bindParam(':dstId', $dst, PDO::PARAM_INT);
                $cnstISql->bindParam(':key', $key, PDO::PARAM_STR);
                $cnstISql->bindParam(':value', $value, PDO::PARAM_STR);
                $cnstSQuery = "select id from csct_constants where data_id=:dstId and data_type=" . ($_REQUEST['et'] ==
                    1 ? "1":"2") . " and ckey=:key";
                $cnstSSql = $this->dbh->prepare($cnstSQuery);
                $cnstSSql->bindParam(':dstId', $dst, PDO::PARAM_INT);
                $cnstSSql->bindParam(':key', $key, PDO::PARAM_STR);

                $cnstUQuery = "update csct_constants set value=:value where data_id=:dstId and data_type=" . ($_REQUEST['et'] ==
                    1 ? "1":"2") . " and ckey=:key";
                $cnstUSql = $this->dbh->prepare($cnstUQuery);
                $cnstUSql->bindParam(':dstId', $dst, PDO::PARAM_INT);
                $cnstUSql->bindParam(':key', $key, PDO::PARAM_STR);
                $cnstUSql->bindParam(':value', $value, PDO::PARAM_STR);

                foreach ($_REQUEST['dest'] as $dst) {
                    foreach ($cResult as $cnstData) {
                        $key = $cnstData['ckey'];
                        $value = $cnstData['cvalue'];
                        $cnstSSql->execute();
                        $cnstIsSet = $cnstSSql->fetch();
                        $cnstSSql->closeCursor();
                        if ($cnstIsSet) {
                            $cnstUSql->execute();
                            $cnstUSql->closeCursor();
                        }
                        else {
                            $cnstISql->execute();
                            $cnstISql->closeCursor();
                        }
                    }
                    reset($cResult);
                }
            }
        }
        //текстовая инфа
        $tables = array(
            '1' => 'csct_pages_text',
            '2' => 'csct_lib_content_names',
            '3' => 'csct_list_items_text',
            '4' => 'csct_page_groups_names');
        $tsets = array();
        if (isset($_REQUEST['header']))
            $tsets[] = $_REQUEST['et'] == 2 ? "name=:name":"header=:header";
        if (isset($_REQUEST['subheader']))
            $tsets[] = "subheader=:subheader";
        if (isset($_REQUEST['title']))
            $tsets[] = "title=:title";
        if (isset($_REQUEST['menu_name']))
            $tsets[] = "menu_name=:menu_name";
        if (isset($_REQUEST['page_preview']))
            $tsets[] = "page_preview=:page_preview";
        if (isset($_REQUEST['page_text']))
            $tsets[] = "page_text=:page_text";
        if ($tsets) {
            $query = "select count(id) from " . $tables[$_REQUEST['et']];
            $query .= " where data_id=:id and lang_id=:lid";
            $issql = $this->dbh->prepare($query);
            $query = "update " . $tables[$_REQUEST['et']];
            $query .= " set " . join(", ", $tsets);
            $query .= " where data_id=:id and lang_id=:lid";
            $sql = $this->dbh->prepare($query);
            $query = "select * from " . $tables[$_REQUEST['et']] . " where data_id=" . $_POST['source'];
            $result = $this->dbh->queryFetchAll($query);
            foreach ($_REQUEST['dest'] as $dst) {
                foreach ($result as $item) {
                    $issql->execute(array(':id' => $dst, ':lid' => $item['lang_id']));
                    $is_item = current($issql->fetch());
                    $issql->closeCursor();
                    if ($is_item) {
                        $params = array();

                        if (isset($_REQUEST['header'])) {
                            if ($_REQUEST['et'] == 2)
                                $params[':name'] = $item['name'];
                            else
                                $params[':header'] = $item['header'];
                        }
                        if (isset($_REQUEST['subheader']))
                            $params[':subheader'] = $item['subheader'];
                        if (isset($_REQUEST['title']))
                            $params[':title'] = $item['title'];
                        if (isset($_REQUEST['menu_name']))
                            $params[':menu_name'] = $item['menu_name'];
                        if (isset($_REQUEST['page_preview']))
                            $params[':page_preview'] = $item['page_preview'];
                        if (isset($_REQUEST['page_text']))
                            $params[':page_text'] = $item['page_text'];
                        $params[':id'] = $dst;
                        $params[':lid'] = $item['lang_id'];
                        $sql->execute($params);
                        $sql->closeCursor();
                    }
                    else {
                        if ($_REQUEST['et'] == 1) {
                            $header = '';
                            $subheader = '';
                            $title = '';
                            $page_text = '';
                            $menu_name = '';
                            $seo_kw = '';
                            $seo_descr = '';
                            $query = "insert into csct_pages_text values ('', '" . $dst .
                                "', :lang_id, :header, :subheader, :title, :page_text, :menu_name, :seo_kw, :seo_descr)";
                            $tsql = $this->dbh->prepare($query);
                            $tsql->bindParam(':lang_id', $item['lang_id'], PDO::PARAM_INT);
                            $tsql->bindParam(':header', $header, PDO::PARAM_STR);
                            $tsql->bindParam(':subheader', $subheader, PDO::PARAM_STR);
                            $tsql->bindParam(':title', $title, PDO::PARAM_STR);
                            $tsql->bindParam(':page_text', $page_text, PDO::PARAM_STR);
                            $tsql->bindParam(':menu_name', $menu_name, PDO::PARAM_STR);
                            $tsql->bindParam(':seo_kw', $seo_kw, PDO::PARAM_STR);
                            $tsql->bindParam(':seo_descr', $seo_descr, PDO::PARAM_STR);
                            $header = $item['header'];
                            $subheader = $item['subheader'];
                            $title = $item['title'];
                            $page_text = $item['page_text'];
                            $menu_name = $item['menu_name'];
                            $seo_kw = $item['seo_kw'];
                            $seo_descr = $item['seo_descr'];
                            $tsql->execute();
                            $tsql->closeCursor();
                        }
                        elseif ($_REQUEST['et'] == 2) {
                            $query = "insert into csct_lib_content_names (data_id, lang_id, name) values ('" . $dst .
                                "', :lid, :name)";
                            $isql = $this->dbh->prepare($query);
                            $isql->bindParam(':lid', $item['lang_id'], PDO::PARAM_INT);
                            $isql->bindParam(':name', $item['name'], PDO::PARAM_STR);
                            $isql->execute();
                            $isql->closeCursor();
                        }
                        elseif ($_REQUEST['et'] == 3) {
                            $query = "insert into csct_list_items_text values ('', '" . $dst .
                                "', :lid, :header, :title, :page_preview, :page_text, :seo_kw, :seo_descr)";
                            $ins_sql = $this->dbh->prepare($query);
                            $ins_sql->bindParam(':lid', $item['lang_id'], PDO::PARAM_INT);
                            $ins_sql->bindParam(':header', $item['header'], PDO::PARAM_STR);
                            $ins_sql->bindParam(':title', $item['title'], PDO::PARAM_STR);
                            $ins_sql->bindParam(':page_text', $item['page_text'], PDO::PARAM_STR);
                            $ins_sql->bindParam(':page_preview', $item['page_preview'], PDO::PARAM_STR);
                            $ins_sql->bindParam(':seo_kw', $item['seo_kw'], PDO::PARAM_STR);
                            $ins_sql->bindParam(':seo_descr', $item['seo_descr'], PDO::PARAM_STR);
                            $ins_sql->execute();
                            $ins_sql->closeCursor();
                        }
                        elseif ($_REQUEST['et'] == 4) {
                            $query = "insert into csct_page_groups_names (data_id, lang_id, name) values ('" . $dst . "', '" . $item['lang_id'] .
                                "', '" . $item['name'] . "')";
                            $this->dbh->exec($query);
                        }
                    }
                }
            }
        }
        //сайты
        if (isset($_REQUEST['site_list'])) {
            $et = $_REQUEST['et'] == 1 ? 0:1;
            $sites = $this->get_sites($_REQUEST['source'], $et);
            $query = "delete from csct_site_links where data_type=" . $et . " and data_id in " . (join(",", $_REQUEST['dest']));
            $this->dbh->exec($query);
            foreach ($_REQUEST['dest'] as $dst)
                foreach ($sites['site_data'] as $site_id => $site_data)
                    $this->dbh->exec("insert into csct_site_links values ('', '" . $dst . "', '" . $et .
                        "', '" . $site_id . "', '" . $site_data['template'] . "', '" . $site_data['page_snp'] . "', '" . $site_data['snp_list'] .
                        "', '" . $site_data['snp_list_item'] . "')");

        }
        //юзеры
        if (isset($_REQUEST['ousers'])) {
            $et = $_REQUEST['et'] == 1 ? 0:1;
            $query = "delete from csct_userlinks where data_type=" . $et . " and data_id in " . (join(",", $_REQUEST['dest']));
            $this->dbh->exec($query);
            $query = "select user_id from csct_userlinks where data_type=" . $et . " and data_id=" . $_REQUEST['source'];
            $users = $this->dbh->query($query)->fetchAll(PDO::FETCH_COLUMN);
            foreach ($_REQUEST['dest'] as $dst)
                foreach ($users as $user)
                    $this->dbh->exec("insert into csct_userlinks values ('', '" . $dst . "', '" . $et . "', '" . $user .
                        "')");
        }
        //группы
        if (isset($_REQUEST['groups'])) {
            $table = $_REQUEST['et'] == 1 ? 'csct_pg_link':'csct_pgr_link';
            $pg_id = 0;
            $num = 1;
            $dst = 0;
            $query = "insert into " . $table . " values ('', :data_id, :pg_id, :num)";
            $pg_sql = $this->dbh->prepare($query);
            $pg_sql->bindParam(':pg_id', $pg_id, PDO::PARAM_INT);
            $pg_sql->bindParam(':num', $num, PDO::PARAM_INT);
            $pg_sql->bindParam(':data_id', $dst, PDO::PARAM_INT);

            $query = "delete from " . $table . " where data_id=:data_id and pg_id=:pg_id";
            $pg_dsql = $this->dbh->prepare($query);
            $pg_dsql->bindParam(':pg_id', $pg_id, PDO::PARAM_INT);
            $pg_dsql->bindParam(':data_id', $dst, PDO::PARAM_INT);
            $query = "select max(num) from " . $table . " where pg_id=:pg_id";
            $pg_nsql = $this->dbh->prepare($query);
            $pg_nsql->bindParam(':pg_id', $pg_id, PDO::PARAM_INT);

            $query = "select pg_id from " . $table . " where data_id=" . $_REQUEST['source'];
            $groups = $this->dbh->query($query)->fetchAll(PDO::FETCH_COLUMN);
            foreach ($_REQUEST['dest'] as $dst) {
                $query = "select pg_id from " . $table . " where data_id=" . $dst;
                $exist_pgl = $this->dbh->query($query)->fetchAll(PDO::FETCH_COLUMN);
                if ($exist_pgl) {
                    foreach ($exist_pgl as $key => $val) {
                        if (isset($groups) && $groups) {
                            foreach ($groups as $nkey => $nval) {
                                if ($val == $nval) {
                                    unset($groups[$nkey]);
                                    unset($exist_pgl[$key]);
                                    break;
                                }
                            }
                        }
                    }
                }

                //новые
                if (isset($groups) && $groups) {
                    foreach ($groups as $pg_id) {
                        $pg_nsql->execute();
                        $nr = $pg_nsql->fetch();
                        $num = $nr ? current($pg_nsql) + 1:1;
                        $pg_nsql->closeCursor();
                        $pg_sql->execute();
                        $pg_sql->closeCursor();
                    }
                }
                //удаляем старые
                if ($exist_pgl) {
                    foreach ($exist_pgl as $pg_id) {
                        $pg_dsql->execute();
                        $pg_dsql->closeCursor();
                    }
                }
            }
        }
        if (isset($_REQUEST['dtmpl_id'])) {

            //получаем данные объекта
            $query = "select * from csct_tdata_fields where data_id='" . $_POST['source'] . "'";
            $tdata_fields = $this->dbh->query($query)->fetchAll();
            //получаем данные по привязкам
            $query = "select * from csct_tdata_flib where data_id='" . $_POST['source'] . "'";
            $tdata_flib = $this->dbh->query($query)->fetchAll();
            $query = "select * from csct_dp_links where data_id=" . $_POST['source'];
            $tdata_opl = $this->dbh->query($query)->fetchAll();
            $query = "select * from csct_ds_links where data_id=" . $_POST['source'];
            $tdata_osl = $this->dbh->queryFetchAll($query);
            //готовим запросы
            $field_id = $item_id = $lang_id = $fnvalue = $lib_id = $item_id = $litem_id = 0;
            $opitem_id = 0;
            $opl_type = 0;
            $fsvalue = 0;
            $fdvalue = '';
            $num = 0;
            $chf_query = "select count(*) from csct_tdata_fields where data_id=:item_id and lang_id=:lang_id and field_id=:field_id";
            $chf_sql = $this->dbh->prepare($chf_query);
            $chf_sql->bindParam(':item_id', $item_id);
            $chf_sql->bindParam(':lang_id', $lang_id);
            $chf_sql->bindParam(':field_id', $field_id);

            $insf_query = "insert into csct_tdata_fields values ('', :item_id, :field_id, :lang_id, :fsvalue, :fnvalue, :fdvalue)";
            $insf_sql = $this->dbh->prepare($insf_query);
            $insf_sql->bindParam(':item_id', $item_id);
            $insf_sql->bindParam(':field_id', $field_id);
            $insf_sql->bindParam(':lang_id', $lang_id);
            $insf_sql->bindParam(':fsvalue', $fsvalue);
            $insf_sql->bindParam(':fnvalue', $fnvalue);
            $insf_sql->bindParam(':fdvalue', $fdvalue);

            $updf_query = "update csct_tdata_fields set fvalue=:fsvalue, fnvalue=:fnvalue, fdvalue=:fdvalue where data_id=:item_id and lang_id=:lang_id and field_id=:field_id";
            $updf_sql = $this->dbh->prepare($updf_query);
            $updf_sql->bindParam(':item_id', $item_id);
            $updf_sql->bindParam(':field_id', $field_id);
            $updf_sql->bindParam(':lang_id', $lang_id);
            $updf_sql->bindParam(':fsvalue', $fsvalue);
            $updf_sql->bindParam(':fnvalue', $fnvalue);
            $updf_sql->bindParam(':fdvalue', $fdvalue);

            $chl_query = "select count(*) from csct_tdata_flib where data_id=:item_id and flib_id=:field_id and lib_id=:lib_id and item_id=:litem_id";
            $chl_sql = $this->dbh->prepare($chl_query);
            $chl_sql->bindParam(':item_id', $item_id);
            $chl_sql->bindParam(':field_id', $field_id);
            $chl_sql->bindParam(':lib_id', $lib_id);
            $chl_sql->bindParam(':litem_id', $litem_id);

            $insl_query = "insert into csct_tdata_flib values ('', :num, :item_id, :field_id, :lib_id, :litem_id)";
            $insl_sql = $this->dbh->prepare($insl_query);
            $insl_sql->bindParam(':item_id', $item_id);
            $insl_sql->bindParam(':num', $num);
            $insl_sql->bindParam(':field_id', $field_id);
            $insl_sql->bindParam(':lib_id', $lib_id);
            $insl_sql->bindParam(':litem_id', $litem_id);

            $chop_query = "select count(*) from csct_dp_links where data_id=:item_id and field_id=:field_id and ltype=:opl_type and item_id=:opitem_id";
            $chop_sql = $this->dbh->prepare($chop_query);
            $chop_sql->bindParam(':item_id', $item_id);
            $chop_sql->bindParam(':field_id', $field_id);
            $chop_sql->bindParam(':opl_type', $opl_type);
            $chop_sql->bindParam(':opitem_id', $opitem_id);

            $insop_query = "insert into csct_dp_links values ('', :item_id, :field_id, :opl_type, :opitem_id)";
            $insop_sql = $this->dbh->prepare($insop_query);
            $insop_sql->bindParam(':opl_type', $opl_type);
            $insop_sql->bindParam(':field_id', $field_id);
            $insop_sql->bindParam(':item_id', $item_id);
            $insop_sql->bindParam(':opitem_id', $opitem_id);
            
            $chop_query = "select count(*) from csct_ds_links where data_id=:item_id and field_id=:field_id and site_id=:opitem_id";
            $chos_sql = $this->dbh->prepare($chop_query);
            $chos_sql->bindParam(':item_id', $item_id);
            $chos_sql->bindParam(':field_id', $field_id);
            $chos_sql->bindParam(':opitem_id', $site_id);

            $insop_query = "insert into csct_ds_links values ('', :item_id, :field_id, :opitem_id)";
            $insos_sql = $this->dbh->prepare($insop_query);
            $insos_sql->bindParam(':field_id', $field_id);
            $insos_sql->bindParam(':item_id', $item_id);
            $insos_sql->bindParam(':opitem_id', $site_id);

            $field_id = 0;

            

            //корневой цикл - список объектов на перенос
            foreach ($_POST['dest'] as $item_id) {
                if (in_array($item_id, $_POST['dest'])) {
                    //вторичный цикл 1 - поля шаблона
                    foreach ($tdata_fields as $tdf) {
                        if (isset($_POST['field_' . $tdf['field_id']])) {
                            $lang_id = $tdf['lang_id'];
                            $field_id = $tdf['field_id'];
                            $fsvalue = $tdf['fvalue'];
                            $fnvalue = $tdf['fnvalue'];
                            $fdvalue = $tdf['fdvalue'];
                            $chf_sql->execute();
                            $chf = current($chf_sql->fetch());
                            $chf_sql->closeCursor();
                            if ($chf) {
                                $updf_sql->execute();
                                $updf_sql->closeCursor();
                            }
                            else {
                                $insf_sql->execute();
                                $insf_sql->closeCursor();
                            }
                        }
                    }
                    reset($tdata_fields);

                    //вторичный цикл 2 - поля привязок
                    foreach ($tdata_flib as $tdlf) {
                        if (isset($_POST['field_' . $tdlf['flib_id']])) {
                            $field_id = $tdlf['flib_id'];
                            $lib_id = $tdlf['lib_id'];
                            $litem_id = $tdlf['item_id'];
                            $num = $tdlf['num'];
                            $chl_sql->execute();
                            $chl = current($chl_sql->fetch());
                            $chl_sql->closeCursor();
                            if (!$chl) {
                                $insl_sql->execute();
                                $insl_sql->closeCursor();
                            }
                        }
                    }
                    reset($tdata_flib);
                    foreach ($tdata_opl as $tdop) {
                        if (isset($_POST['field_' . $tdop['field_id']])) {
                            $field_id = $tdop['field_id'];
                            $opitem_id = $tdop['item_id'];
                            $opl_type = $tdop['ltype'];
                            $chop_sql->execute();
                            $chop = current($chop_sql->fetch());
                            $chop_sql->closeCursor();
                            if (!$chop) {
                                $insop_sql->execute();
                                $insop_sql->closeCursor();
                            }
                        }
                    }
                    foreach ($tdata_osl as $tdop) {
                        if (isset($_POST['field_' . $tdop['field_id']])) {
                            $field_id = $tdop['field_id'];
                            $site_id = $tdop['site_id'];
                            $chos_sql->execute();
                            $chos = current($chos_sql->fetch());
                            $chos_sql->closeCursor();
                            if (!$chos) {
                                $insos_sql->execute();
                                $insos_sql->closeCursor();
                            }
                        }
                    }
                }
            }
        }
    }

    function trigger_execute($trigger_key, $id = 0)
    {
        $query = "select * from csct_triggers where trgr_cname='" . $trigger_key . "'";
        $result = $this->dbh->queryFetchAll($query);
        if ($result) {
            foreach ($result as $item) {
                try {
                    eval($item['content']);
                }
                catch (exception $e) {
                    //2log "Error (File: ".$e->getFile().", line ".$e->getLine()."): ".$e->getMessage();
                }
            }
        }
    }

    function get_triggers()
    {
        $query = "select * from csct_triggers";
        $result = $this->dbh->queryFetchAll($query);
        return $result;
    }

    function del_mfield($field_id, $group_id)
    {
        if ($group_id) {
            $query = "select count(f1.id) from csct_tgdata_fields f1, csct_tgdata_fields f2 where f2.id=" . $field_id .
                " and f2.field_id=f1.field_id and f2.lang_id=f1.lang_id and f1.group_id=" . $group_id;
            $result = $this->dbh->queryFetchRow($query);
            if (current($result) > 1)
                $this->dbh->exec("delete from csct_tgdata_fields where id=" . $field_id);
        }
        else {
            $query = "select count(f1.id) from csct_tdata_fields f1, csct_tdata_fields f2 where f2.id=" . $field_id .
                " and f2.field_id=f1.field_id and f2.lang_id=f1.lang_id";
            $result = $this->dbh->queryFetchRow($query);
            if (current($result) > 1)
                $this->dbh->exec("delete from csct_tdata_fields where id=" . $field_id);
        }
    }

    function add_mfield()
    {
        if (isset($_POST['group_id']) && $_POST['group_id'])
            $query = "insert into csct_tgdata_fields values ('', :tdata_id, '" . $_POST['group_id'] .
                "', :field_id, :lang_id, '', 0, '')";
        else
            $query = "insert into csct_tdata_fields values ('', :tdata_id, :field_id, :lang_id, '', 0, '')";
        $fisql = $this->dbh->prepare($query);
        $fisql->bindParam(':tdata_id', $_POST['tdata_id'], PDO::PARAM_INT);
        $fisql->bindParam(':field_id', $_POST['field_id'], PDO::PARAM_INT);
        $fisql->bindParam(':lang_id', $_POST['lid'], PDO::PARAM_INT);
        $fisql->execute();
        $fid = $this->dbh->lastInsertId();
        $fisql->closeCursor();
        return $fid;
    }

    function get_df_data()
    {
        $query = "select fvalue from csct_tdata_fields where data_id=" . $_POST['tdata_id'] .
            " and field_id=" . $_POST['field_id'] . " and lang_id=" . $_POST['lid'];
        if (isset($_POST['fld_id']))
            $query .= " and id=" . $_POST['fld_id'];
        $result = $this->dbh->queryFetchRow($query);
        if ($result)
            return current($result);
        else
            return '';
    }

    function get_comments($tdata_id = null, $tdata_type = 1, $status = null, $limit = 15, $page = 1, $show_page = false)
    {
        $conditions = array();
        if ($tdata_id)
            $conditions[] = "data_id=" . $tdata_id;
        if ($tdata_type)
            $conditions[] = "data_type=" . $tdata_type;
        if ($status !== null)
            $conditions[] = "status=" . $status;
        $query = "select count(id) from csct_comments";
        if ($conditions)
            $query .= " where " . join(" and ", $conditions);
        $qty = current($this->dbh->queryFetchRow($query));
        $query = "select *, DATE_FORMAT(cmnt_date, '%d.%m.%Y %H:%i') cmnt_fdate from csct_comments";
        if ($conditions)
            $query .= " where " . join(" and ", $conditions);
        $start = ($page - 1) * $limit;
        $query .= " limit " . $start . ", " . $limit;
        $result = $this->dbh->queryFetchAll($query);
        $query = "select * from site_users where id=:user_id";
        $user_id = 0;
        $sql = $this->dbh->prepare($query);
        $sql->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        if ($show_page) {
            $data_id = 0;
            $query = "select header from " . ($tdata_type == 1 ? "csct_pages_text":"csct_list_items_text") .
                " where data_id=:data_id";
            $tsql = $this->dbh->prepare($query);
            $tsql->bindParam(':data_id', $data_id, PDO::PARAM_INT);
        }
        foreach ($result as $key => $item) {
            if ($item['user_id'] != 0) {
                $user_id = $item['user_id'];
                $sql->execute();
                $user_data = $sql->fetch();
                $sql->closeCursor();
                $result[$key]['user_name'] = $user_data['fio'];
                $result[$key]['user_contacts'] = $user_data['email'] . "|" . $user_data['tel'];
            }
            if ($show_page) {
                $data_id = $item['data_id'];
                $tsql->execute();
                $tdata = $tsql->fetch();
                $tsql->closeCursor();
                $result[$key]['page_header'] = $tdata['header'];
            }
        }
        return array('qty' => $qty, 'data' => $result);
    }

    function comment_delete($id)
    {
        $query = "delete from csct_comments where id=" . $id;
        $this->dbh->exec($query);
    }

    function isite_update()
    {
        $template = isset($_POST['template']) ? $_POST['template']:-1;
        $page_snp = isset($_POST['page_snp']) ? $_POST['page_snp']:-1;
        $snp_list = isset($_POST['snp_list']) ? $_POST['snp_list']:-1;
        $snp_list_item = isset($_POST['snp_list_item']) ? $_POST['snp_list_item']:-1;
        $qry = "update csct_site_links set template='" . $template . "', page_snp='" . $page_snp .
            "', snp_list='" . $snp_list . "', snp_list_item='" . $snp_list_item .
            "' where data_type=0 and data_id=" . $_POST['tdata_id'] . " and site_id=" . $_POST['site_id'];
        $this->dbh->exec($qry);
    }

    function sel_site_users($query)
    {
        $qsel = "select * from site_users where status=1";
        if ($query)
            $qsel .= " and (u_login like '%" . $query . "%' or email like '%" . $query . "%' or fio like '%" . $query .
                "%' or id like '%" . $query . "%')";
        return $this->dbh->queryFetchAll($qsel);
    }

    function delTGroup($groupId)
    {
        $query = "delete from csct_tdata_groups where id=" . $groupId;
        $this->dbh->exec($query);
    }

    function addTGroup()
    {
        $query = "select * from csct_dtmpl_groups where id=" . $_POST['tGroupId'];
        $result = $this->dbh->queryFetchRow($query);
        $query = "insert into csct_tdata_groups (data_id, dtmpl_id, group_id, name) values ('" . $_POST['tDataId'] .
            "', '" . $_POST['dTmplId'] . "', '" . $_POST['tGroupId'] . "', '" . $result['name'] . "')";
        $this->dbh->exec($query);
    }

    function updTGroupName($tGroupID, $name)
    {
        $query = "update csct_tdata_groups set name=:name where id=" . $tGroupID;
        $sql = $this->dbh->prepare($query);
        $sql->bindParam(':name', $name, PDO::PARAM_STR);
        $sql->execute();
        $sql->closeCursor();
    }

    function delCnst($cnstId)
    {
        $query = "delete from csct_constants where id=" . $cnstId;
        $this->dbh->exec($query);
    }

    function addCnst()
    {
        $query = "insert into csct_constants (data_id, data_type, ckey, cvalue) values ('" . $_POST['tDataId'] .
            "', '" . $_POST['tDataType'] . "', :key, :value)";
        $sql = $this->dbh->prepare($query);
        $key = strtoupper($this->trlit($_POST['cnstKey']));
        $sql->bindParam(':value', $_POST['cnstVal'], PDO::PARAM_STR);
        $sql->bindParam(':key', $key, PDO::PARAM_STR);
        $sql->execute();
        $id = $this->dbh->lastInsertId();
        $sql->closeCursor();
        return array('id' => $id, 'key' => $key);
    }

    function updCnst()
    {
        if ($_POST['data'] == 'key')
            $query = "update csct_constants set ckey=:name where id=" . $_POST['pk'];
        else
            $query = "update csct_constants set cvalue=:name where id=" . $_POST['pk'];
        $sql = $this->dbh->prepare($query);
        $sql->bindParam(':name', $_POST['value'], PDO::PARAM_STR);
        $sql->execute();
        $sql->closeCursor();
    }

}
?>