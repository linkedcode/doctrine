<?php

namespace Linkedcode\Doctrine\Utils;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

class Nested
{
    private const ROOT_NODE = 'ROOT';
    private string $table = '';

    private string $lftcol = 'lft';
    private string $rgtcol = 'rgt';
    private string $colname = 'name';
    private string $lvlcol = 'level';
    private string $parentcol = 'parent_id';
    private string $keycol = 'id';

    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function addNode(string $name, int $parentId = 0)
    {
        $fields = array(
            $this->colname => $name
        );

        if ($parentId == 0) {
            $fields[$this->lftcol] = 1;
            $fields[$this->rgtcol] = 2;
            $fields[$this->lvlcol] = 0;
            $fields[$this->parentcol] = 0;

            $this->getConn()->insert($this->table, $fields);

            return $this->getConn()->lastInsertId();
        }

        if ($this->hasNodes($parentId) == false) {
            //echo "No tiene hijos. Primer hijo<br/>";
            return $this->__addNodeV2($name, $parentId);
        } else {
            //echo "Tiene hijos. Segundo o mas hijos.<br/>";
            return $this->__addNodeV1($name, $parentId);
        }
    }

    private function firstNode()
    {
        // AND {$this->colsection}={$this->section}
        $sql = "SELECT * FROM {$this->table} WHERE {$this->lvlcol} = 0 AND {$this->parentcol} = 0";
        $stmt = $this->getConn()->prepare($sql);
        $result = $stmt->executeQuery();

        if ($result->rowCount() == 0) {
            return $this->addNode(self::ROOT_NODE, 0);
        } else {
            $row = $result->fetchAllAssociative();
            return $row[0]['id'];
        }
    }

    public function setTable(string $table): static
    {
        $this->table = $table;
        return $this;
    }

    private function hasNodes(int $parentId)
    {
        $sql = "SELECT ({$this->rgtcol} - {$this->lftcol}) AS diff 
            FROM {$this->table} 
            WHERE {$this->keycol} = {$parentId}";

        $result = $this->getConn()->executeQuery($sql);

        if ($result->rowCount() === 0) {
            return false;
        } else {
            return true;
        }
    }

    private function getConn(): Connection
    {
        return $this->em->getConnection();
    }

    private function __addNodeV2(string $name, int $parentId)
    {
        $this->getConn()->executeQuery("LOCK TABLE {$this->table} WRITE");

        /*if ($this->extra_id != null && $this->section !== null) {
            $row = $this->adapter->prepare("SELECT MAX(`{$this->extra_id}`) as id FROM {$this->table} WHERE section='{$this->section}'")->execute()->fetch();
            $id = $row['id'] + 1;

            if ($this->_query("SELECT @myLeft := {$this->lftcol}, @myLevel := level FROM {$this->table} WHERE {$this->extra_id} = '{$parent_id}' AND section='{$this->section}';") == false) {
                echo $db->error();
            }

            if ($this->_query("UPDATE {$this->table} SET {$this->rgtcol} = {$this->rgtcol} + 2 WHERE {$this->rgtcol} > @myLeft AND {$this->colsection}={$this->section};") == false) {
                echo $db->error();
            }

            if ($this->_query("UPDATE {$this->table} SET {$this->lftcol} = {$this->lftcol} + 2 WHERE {$this->lftcol} > @myLeft AND {$this->colsection}={$this->section};") == false) {
                echo $db->error();
            }

            $this->fields[$this->lftcol] = "@myLeft + 1";
            $this->fields[$this->rgtcol] = "@myLeft + 2";
            $this->fields['level'] = "@myLevel + 1";

            if ($this->adapter->insert($this->table, $this->fields) == false) {
                echo $db->error();
                echo $sql;
            }
        } else {*/
        $sql1 = "SELECT @myLeft := {$this->lftcol}, @myLevel := {$this->lvlcol} FROM {$this->table} WHERE {$this->keycol} = {$parentId}";
        $this->getConn()->executeQuery($sql1);

        $sql2 = "UPDATE {$this->table} SET {$this->rgtcol} = {$this->rgtcol} + 2 WHERE {$this->rgtcol} > @myLeft";
        $this->getConn()->executeQuery($sql2);

        $sql3 = "UPDATE {$this->table} SET {$this->lftcol} = {$this->lftcol} + 2 WHERE {$this->lftcol} > @myLeft";
        $this->getConn()->executeQuery($sql3);

        $sql4 = "INSERT INTO {$this->table} 
            ({$this->colname}, {$this->lftcol}, {$this->rgtcol}, {$this->lvlcol}, {$this->parentcol}) VALUES
            ('{$name}', @myLeft + 1, @myLeft + 2, @myLevel + 1, {$parentId})";

        $this->getConn()->executeQuery($sql4);
        $id = $this->getConn()->lastInsertId();

        $this->getConn()->executeQuery("UNLOCK TABLES;");

        return $id;
    }

    private function __addNodeV1(string $name, int $parentId)
    {
        $this->getConn()->executeQuery("LOCK TABLE {$this->table} WRITE");

        $sql1 = "SELECT @myRight := {$this->rgtcol}, @myLevel := {$this->lvlcol} FROM {$this->table} WHERE {$this->keycol} = {$parentId}";
        $this->getConn()->executeQuery($sql1);

        $sql2 = "UPDATE {$this->table} SET {$this->rgtcol} = {$this->rgtcol} + 2 WHERE {$this->rgtcol} >= @myRight";
        $this->getConn()->executeQuery($sql2);

        $sql3 = "UPDATE {$this->table} SET {$this->lftcol} = {$this->lftcol} + 2 WHERE {$this->lftcol} >= @myRight";
        $this->getConn()->executeQuery($sql3);

        $sql4 = "INSERT INTO {$this->table} 
            ({$this->colname}, {$this->lftcol}, {$this->rgtcol}, {$this->lvlcol}, {$this->parentcol}) VALUES
            ('{$name}', @myRight, @myRight + 1, @myLevel + 1, {$parentId})";

        $this->getConn()->executeQuery($sql4);
        $id = $this->getConn()->lastInsertId();

        $this->getConn()->executeQuery("UNLOCK TABLES");

        return $id;
    }

    public function rebuild(int $parentId = 0, int $left = 0, $debug = false)
    {
        // the right value of this node is the left value + 1
        static $r = 0;
        static $level = -1;

        $r++;

        $right = $left + 1;

        $sql = "SELECT {$this->keycol} FROM {$this->table} WHERE {$this->parentcol} = {$parentId}";

        $result = $this->getConn()->executeQuery($sql);
        $rows = $result->fetchAllAssociative();

        foreach ($rows as $row) {
            $level++;

            // recursive execution of this function for each
            // child of this node
            // $right is the current right value, which is
            // incremented by the rebuild_tree function

            $right = $this->rebuild($row[$this->keycol], $right, $debug);
            $level--;
        }

        // we've got the left value, and now that we've processed
        // the children of this node we also know the right value
        $sql1 = "UPDATE {$this->table} SET {$this->lftcol} = {$left}, {$this->rgtcol} = {$right}, {$this->lvlcol} = {$level} WHERE ";
        $sql1 .= " {$this->keycol} = {$parentId}";
        
        $this->getConn()->executeQuery($sql1);
        
        // return the right value of this node + 1
        return $right + 1;
    }
    /*
    public function setLft($lft) {
        $this->lftcol = $lft;
        return $this;
    }

    public function setRgt($rgt) {
        $this->rgtcol = $rgt;
        return $this;
    }

    public function setLevel($level) {
        $this->level = $level;
        return $this;
    }

    public function setName($name) {
        $this->colname = $name;
        return $this;
    }

    function findAll($conditions = null) {
        $arr = array();

        if ($conditions == null) {
            $conditions = array();
            $conditions[] = "node.parent_id='0'";
            if (defined('SITE_ID')) {
                $conditions[] = "node.site_id='" . SITE_ID . "'";
            }
        } else if (!is_array($conditions)) {
            $conditions = array($conditions);
        }


        $sql = "SELECT *, node.{$this->key}, node.{$this->colname}
            FROM {$this->table} AS node,
            {$this->table} AS parent
            WHERE (node.lft BETWEEN parent.{$this->lftcol} AND parent.{$this->rgtcol})
            AND " . implode(" AND ", $conditions) . " ORDER BY node.{$this->lftcol};";
        //echo $sql;
        $r = $this->_query($sql);
        //echo $db->error();
        while ($fila = $db->fetch_assoc($r)) {
            //pr($fila);
            $tmp[$this->table] = $fila;
            $arr[] = $tmp;
        }

        return $arr;
    }

    public function getFullTree($exclude = null)
    {
        $sql = "SELECT * FROM {$this->table} ";
        //$sql .= "WHERE level > 0 ";
        $sql .= "WHERE 1=1 ";

        if ($this->section !== null) {
            $sql .= " AND {$this->colsection} = {$this->section} ";
        }

        if (defined('SITE_ID') && $this->useSiteId === true && $this->colsection != 'site_id') {
            $sql .= "AND site_id=" . SITE_ID . " ";
        }

        if ($exclude) {
            $excludeSQL = "SELECT node.id FROM {$this->table} AS node, "
                . $this->table . " AS parent WHERE (node.{$this->lftcol} BETWEEN parent.{$this->lftcol} AND "
                . "parent.{$this->rgtcol}) AND parent.level > 0 AND parent.id = {$exclude}";

            if ($this->section !== null) {
                $excludeSQL .= " AND parent.{$this->colsection} = {$this->section} ";
            }


            $sql .= " AND id NOT IN ({$excludeSQL})";
        }

        $sql .= "ORDER BY {$this->lftcol}";

        $rows = $this->adapter->prepare($sql)->execute()->fetchAll();
        return $rows;
    }

    public function getTree($id = null, $exclude = null, $siteId = 0, $conditions = array())
    {
        if (empty($id)) {
            $rows = $this->getFullTree($exclude);
        } else {

            // Ejemplo
            // SELECT node.name FROM nested_category AS node, nested_category AS parent WHERE
            // node.lft BETWEEN parent.lft AND parent.rgt AND parent.name = 'ELECTRONICS' ORDER BY node.lft;

            $sql = "SELECT node.* FROM {$this->table} AS node, {$this->table} AS parent ";
            $sql .= " WHERE (node.lft BETWEEN parent.{$this->lftcol} AND parent.{$this->rgtcol}) ";
            //$sql .= " AND node.{$this->key} > {$this->rootid} ";
            $sql .= " AND parent.level > 0 ";

            if ($conditions) {
                $sql .= " AND " . implode(" AND ", $conditions);
            }

            if ($siteId > 0) {
                $sql .= " AND node.{$this->colsection} = " . $siteId . " ";
            }

            $sql .= " AND parent.id=" . $id . " ";

            // Cuando estamos editando un nodo, debemos evitar situarlo debajo
            // de todos los que dependen de el
            if ($exclude !== null) {
                $sql2 = "SELECT node.id FROM {$this->table} AS node, {$this->table} AS parent ";
                $sql2 .= " WHERE (node.lft BETWEEN parent.{$this->lftcol} AND parent.{$this->rgtcol}) ";
                //$sql2 .= " AND node.{$this->key} > {$this->rootid} ";
                $sql2 .= " AND parent.level > 0 ";
                $sql2 .= " AND parent.id=" . $exclude;
                if ($siteId > 0) {
                    $sql2 .= " AND parent.{$this->colsection} = " . $siteId . " ";
                }
                $sql .= " AND node.id NOT IN ({$sql2}) ";
            }

            $sql .= " ORDER BY node.{$this->lftcol};";

            $rows = $this->adapter->prepare($sql)->execute()->fetchAll();
        }

        $tree = $this->formatTreeAsArray($rows);

        return $tree;
    }

    private function formatTreeAsArray($rows)
    {
        $tree = array();

        foreach ($rows as $row) {
            $key = $row[$this->key];
            $counter = $row[$this->level] - 1;
            if ($counter < 0) {
                $counter = 0;
            }
            $spacer = str_repeat("--", $counter);
            $value = $row[$this->colname];

            $tree[] = array(
                'id' => $key,
                'level' => $row[$this->level],
                'name' => trim($spacer . " " . $value)
            );
        }

        return $tree;
    }

    private function formatTree($rows)
    {
        $tree = array();

        foreach ($rows as $row) {
            $key = $row[$this->key];
            $counter = $row[$this->level] - 1;
            if ($counter < 0) {
                $counter = 0;
            }
            $spacer = str_repeat("--", $counter);
            $value = $row[$this->colname];
            $tree[$key] = trim($spacer . " " . $value);
        }

        return $tree;
    }

    public function findNode($id)
    {
        if ($this->section !== null) {
            $sql = "SELECT * FROM {$this->table} WHERE `{$this->extra_id}`={$id} AND {$this->colsection}={$this->section}";
        } else {
            $sql = "SELECT * FROM {$this->table} WHERE `{$this->key}`={$id}";
        }
        $r = $this->_query($sql);
        //echo $sql;

        if ($r == false) {
            echo $sql;
            error_log($sql . $db->error());
        } else {
            return $db->fetch_assoc($r);
        }
    }

    function updateLevels() {
        $sql = "SELECT node.{$this->key} as id, (COUNT(parent.{$this->key}) - 1) AS depth
		FROM {$this->table} AS node,
		{$this->table} AS parent
		WHERE node.{$this->lftcol} BETWEEN parent.{$this->lftcol} AND parent.{$this->rgtcol}
		GROUP BY node.{$this->key}
		ORDER BY node.{$this->lftcol};";
        //echo $sql;
        //die();
        $r = $this->_query($sql);
        if ($r == false) {
            echo $sql;
        }

        $i = 0;
        while ($fila = $db->fetch_assoc($r)) {
            $i++;
            //$this->_query("UPDATE {$this->table} SET level='{$fila['depth']}'");
            echo $db->error();
            if ($i == 50) {
                sleep(5);
                $i = 0;
            }
        }
    }

    function findFinals($conditions = array(), $fields = null, $order = null, $limit = 30, $page = 1) {
        //echo $conditions;
        if (is_numeric($conditions)) {
            $db = & DB::getInstance();
            $sql = "SELECT * FROM {$this->table} WHERE {$this->key}={$conditions}";
            //echo $sql;
            $r = $this->_query($sql);
            $row = $db->fetch_assoc($r);
            //pr($row);
            $conditions = array("`{$this->lftcol}`>{$row[$this->lftcol]}", "`{$this->rgtcol}`<{$row[$this->rgtcol]}");
            //pr($conditions);
        }

        if (is_string($conditions)) {
            $conditions = array($conditions);
        }


        $conditions[] = "`{$this->rgtcol}`=`{$this->lftcol}`+1";
        $init = ($page - 1) * $limit;

        $sql = "SELECT * FROM {$this->table} WHERE ";
        $sql .= implode(" AND ", $conditions);
        if ($order !== null) {
            $sql .= " ORDER BY " . $order;
        }
        $sql .= " LIMIT {$init}, {$limit}";
        //echo "Nested_Component->findFinals: ". $sql;

        $res = $this->__results($sql);
        return $res;
    }

    function findRoots() {
        $sql = "SELECT node.{$this->key}, node.{$this->colname}, (COUNT(parent.{$this->colname}) - 1) AS depth
		FROM {$this->table} AS node,
		{$this->table} AS parent
		WHERE node.{$this->lftcol} BETWEEN parent.{$this->lftcol} AND parent.{$this->rgtcol}
		GROUP BY node.{$this->colname}
		HAVING depth = 0
		ORDER BY node.{$this->lftcol};";
        //echo $sql;

        $r = $this->_query($sql);
        while ($fila = $db->fetch_assoc($r)) {
            $ret[$fila[$this->key]] = $fila[$this->colname];
        }

        return $ret;
    }

    // Devuelve true si Casa (X) es hija de Inmuebles (Y).
    // No funciona con la tabla 'mercadolibre'
    // si no tiene el externid definido
    function isChild($child, $parent) {
        $_ids = array_keys($this->findPath($parent));
        //pr($_ids);

        if (in_array($parent, $_ids)) {
            return true;
        } else {
            return false;
        }
    }

    public function findPath($id, $uri = false, $key = null) {
        //$db = & DB::getInstance();
        $ret = array();

        $sql = "SELECT parent.{$this->colname}";

        if ($key !== null) {
            $sql .= ", parent.{$key}";
        } else {
            $sql .= ", parent.{$this->key}";
        }

        if ($uri === true) {
            $sql .= ", parent.niceuri";
        }

        $sql .= " FROM {$this->table} AS node, {$this->table} AS parent WHERE node.{$this->lftcol}
         BETWEEN parent.{$this->lftcol} AND parent.{$this->rgtcol} AND node.{$this->key} = '{$id}'";
        if ($this->section != null) {
            $sql .= " AND parent.{$this->colsection}='{$this->section}'";
        }
        $sql .= " ORDER BY parent.{$this->lftcol};";

        //echo $sql;
        //$r = $this->_query($sql);
        $r = $this->adapter->prepare($sql)->execute()->fetchAll();

        if ($key === null) {
            $key = $this->key;
        }

        //while ($fila = $r->getRow($r))
        foreach ($r as $fila) {
            if ($uri === true) {
                if ($fila['niceuri'] != '') {
                    $ret[$fila['niceuri']] = $fila[$this->colname];
                } else {
                    $ret[$fila[$key]] = $fila[$this->colname];
                }
            } else {
                $ret[$fila[$key]] = $fila[$this->colname];
            }
        }

        return $ret;
    }

    function findChilds2() {
        $sql = "SELECT node.{$this->key}, node.{$this->colname}, (COUNT(parent.{$this->key})-1) AS depth
            FROM {$this->table} AS node,
            {$this->table} AS parent
            WHERE node.{$this->lftcol} BETWEEN parent.{$this->lftcol} AND parent.{$this->rgtcol}
            GROUP BY node.{$this->key}
            ORDER BY node.{$this->lftcol};";

        //echo $sql;
        $result = $this->da->fetch($sql);
        //pr($result);
        if ($result == false) {
            //echo $sql;
        }

        while ($fila = $result->getRow($result)) {
            $ret[$fila[$this->key]] = $fila[$this->colname];
        }

        return $ret;
    }

    function addNodeToTree($tree, $node) {
        //echo "<hr>";pr($tree);pr($node);echo "<hr>";
        static $i = 0;
        $i++;
        if ($i == 100) {
            die();
        }

        foreach ($tree as $kb => $branch) {
            if ($branch[$this->extra_id] == $node[$this->parent]) {
                $node['_children'] = array();
                $tree[$kb]['_children'][] = $node;
                return $tree;
            }

            if (is_array($tree[$kb]['_children']) && count($tree[$kb]['_children']) > 0) {
                $tree[$kb]['_children'] = $this->addNodeToTree($tree[$kb]['_children'], $node);
            }
        }



        foreach ($tree[$kb]['_children'] as $kc => $branch) {
            //$tree[$kb]['_children'][$kc] = $this->addNodeToTree($branch, $node);
        }

        return $tree;
    }

    function rows2tree($rows, $node_id = 1) {
        static $tree = array();

        ///pr($rows);
        foreach ($rows as $key => $node) {
            //pr($row);
            if ($node[$this->parent] == $node_id) {
                $node['_children'] = array();
                $tree[] = $node;
                unset($rows[$key]);
            } else {
                $tree = $this->addNodeToTree($tree, $node, $node[$this->parent]);
            }

            //echo $row[$this->extra_id]."</br>";
            //echo $row[$this->key]."</br>";
        }

        //pr($tree);
        return $tree;
    }

    function findChild($id, $levels = null, $showLevel = 0, $hideSection = true) {
        $ret = array();

        if ($this->extra_id !== null) {
            $key = $this->extra_id;
        } else {
            $key = $this->extra_id = $this->key;
        }

        $sql = "
            SELECT
                node.*
            FROM
                {$this->table} AS node,
                (SELECT node.* FROM {$this->table} AS node WHERE 1=1 ";

        if ($id !== null) {
            $sql .= " AND node.{$key}={$id} ";
        }

        if ($this->section !== null) {
            $sql .= " AND node.{$this->colsection}={$this->section}";
        }
        $sql .= ") AS parent
            WHERE
                (node.{$this->lftcol} BETWEEN parent.{$this->lftcol} AND parent.{$this->rgtcol}) ";

        if ($levels != null) {
            $sql .= " AND (node.{$this->level} BETWEEN parent.{$this->level}+1 AND parent.{$this->level} + " . (string) $levels . ")";
        }

        if ($this->section !== null) {
            $sql .= " AND node.{$this->colsection}='{$this->section}'";
        }

        if ($this->site_id !== null) {
            $sql .= " AND node.site_id={$this->site_id}";
        }

        if ($hideSection === true) {
            if ($this->section !== null) {
                $sql .= " AND node.{$this->extra_id}<>{$id}";
            } else {
                $sql .= " AND node.{$key}<>{$id}";
            }
        }

        $sql .= " GROUP BY node.{$key}";

        if ($showLevel === 2 || $showLevel === 3) {
            //$sql .= " ORDER BY {$key};";
            $sql .= " ORDER BY {$this->colname};";
        } else {
            if ($levels == 1) {
                $sql .= " ORDER BY {$this->colname};";
            } else {
                $sql .= " ORDER BY node.{$this->lftcol};";
            }
        }

        //echo "<blockquote>{$sql}</blockquote>";

        $r = $this->adapter->prepare($sql)->execute()->fetchAll();
        //pr($r);

        if ($r === false) {
            echo $sql;
            echo $db->error();
            echo "findChilds [key:{$this->key}] - [$sql]<br/>";
        }

        //while ($fila = $r->getRow())
        foreach ($r as $fila) {
            //pr($fila);
            if ($showLevel == 0) {
                $value = $fila[$this->colname];

                if ($this->indent !== null) {
                    $value = str_repeat($this->indent, $fila['level'] - 1) . $value;
                }

                if (!empty($this->keyCol)) {
                    $ret[$fila[$this->keyCol]] = $value;
                } else {
                    $ret[$fila[$key]] = $value;
                }
            } else if ($showLevel == 1) {
                $ret[$fila[$key]]['name'] = $fila[$this->colname];
                $ret[$fila[$key]]['niceuri'] = isset($fila['niceuri']) ? $fila['niceuri'] : "";
                $ret[$fila[$key]]['level'] = $fila[$this->level];

                if (isset($fila['externid'])) {
                    $ret[$fila[$key]]['externid'] = $fila['externid'];
                }

                if (isset($fila['product_type_id'])) {
                    $ret[$fila[$key]]['product_type_id'] = $fila['product_type_id'];
                }
            } else if ($showLevel == 2) {
                $tmp[$this->table] = $fila;
                $ret[] = $tmp;
            } else if ($showLevel == 3) {
                //pr($fila);
                $rows[] = $fila;
            }
        }

        if ($showLevel === 3) {
            $ret = $this->rows2tree($rows, $id);
        }

        return $ret;
    }

    function _childs($id, $levels = null, $pre = '') {
        $ret = array();

        if ($levels == null) {
            $sqlevel = ""; //" HAVING depth <> 0 ";
        } else {
            $sqlevel = " HAVING depth <= {$levels} AND depth > 0 ";
        }

        //CONCAT( REPEAT('{$pre}', (COUNT(parent.{$this->key}) - 1) ), node.{$this->colname}) as {$this->colname}, (COUNT(parent.{$this->key}) - (sub_tree.depth + 1)) AS depth
        $sql = "
		SELECT
			node.{$this->key},
			node.{$this->colname}, (COUNT(parent.{$this->key}) - (sub_tree.depth + 1)) AS depth
		FROM {$this->table} AS node,
			{$this->table} AS parent,
			{$this->table} AS sub_parent,
			(
				SELECT node.{$this->key}, (COUNT(parent.{$this->key}) - 1) AS depth
				FROM {$this->table} AS node,
				{$this->table} AS parent
				WHERE (node.{$this->lftcol} BETWEEN parent.{$this->lftcol} AND parent.{$this->rgtcol})
				AND node.{$this->key} = '{$id}'
				GROUP BY node.{$this->key}
				ORDER BY node.{$this->lftcol}
			) AS sub_tree
		WHERE node.{$this->lftcol} BETWEEN parent.{$this->lftcol} AND parent.{$this->rgtcol}
			AND node.{$this->lftcol} BETWEEN sub_parent.{$this->lftcol} AND sub_parent.{$this->rgtcol}
			AND sub_parent.{$this->key} = sub_tree.{$this->key}
		GROUP BY node.{$this->key}
		{$sqlevel}
		ORDER BY node.{$this->lftcol};";

        //echo $sql;
        $r = $this->_query($sql);
        if ($r == false) {
            echo $sql;
        }
        while ($fila = $db->fetch_assoc($r)) {
            //pr($fila);
            $ret[$fila[$this->key]] = $fila[$this->colname];
        }

        return $ret;
    }


    public function deleteNode($id)
    {
        if ($this->_query("LOCK TABLE {$this->table} WRITE;") == false)
            echo $db->error();
        if ($this->_query("SELECT @myLeft := {$this->lftcol}, @myRight := {$this->rgtcol}, @myWidth :={$this->rgtcol} - {$this->lftcol} + 1 FROM {$this->table} WHERE {$this->key} = '{$id}';") == false)
            echo $db->error();
        if ($this->_query("DELETE FROM {$this->table} WHERE {$this->lftcol} BETWEEN @myLeft AND @myRight;") == false)
            echo $db->error();
        if ($this->_query("UPDATE {$this->table} SET {$this->rgtcol} = {$this->rgtcol} - @myWidth WHERE {$this->rgtcol} > @myRight;") == false)
            echo $db->error();
        if ($this->_query("UPDATE {$this->table} SET {$this->lftcol} = {$this->lftcol} - @myWidth WHERE {$this->lftcol} > @myRight;") == false)
            echo $db->error();
        if ($this->_query("UNLOCK TABLES;") == false)
            echo $db->error();
    }

    function setCats($id, $table = null) {
        //pr($this);//pr($this->controller);
        if ($table != null) {
            $this->table = $table;
        }

        $model = $this->controller->model;

        if (isset($this->controller->data[$model])) {

            $data = $this->controller->data[$model];
            $cat1 = $this->findChild($id, 1);
            asort($cat1);

            $this->controller->set('cat1', array(-1 => ' - ') + $cat1);

            for ($i = 1; $i < 10; $i++) {
                if (isset($data['cat' . $i]) && $data['cat' . $i] > 0) {
                    $j = $i + 1;
                    $v = $data['cat' . $i];
                    $cat[$j] = $this->findChild($v, 1);
                    asort($cat[$j]);
                    $this->controller->set('cat' . $j, array("-1" => " - ") + $cat[$j]);
                } else {
                    break;
                }
            }
        }
    }

    function generateTree($id, $levels = 1, $wheres = array()) {
        $items = $this->findChild($id, $levels, true, false);
        //pr($items);
        return $items;
    }

    private function _query($sql)
    {
        $res = $this->adapter->prepare($sql)->execute();

        return $res;
    }

    public function countSubnodes($id = null)
    {
        $wheres = array();
        $sql = "SELECT * FROM {$this->table}";
        if ($this->section !== null) {
            $wheres[] = "{$this->colsection}='{$this->section}'";
        }
        if ($id !== null) {
            $wheres[] = "{$this->key}={$id}";
        }
        if (count($wheres) > 0) {
            $sql .= " WHERE " . implode(" AND ", $wheres);
        }
        $rows = $this->adapter->prepare($sql)->execute()->fetchAll();
        foreach ($rows as $row) {
            $sql2 = "SELECT COUNT(*) AS q FROM {$this->table} WHERE {$this->lftcol}>{$row[$this->lftcol]} AND {$this->rgtcol}<{$row[$this->rgtcol]}";
            $rows2 = $this->adapter->prepare($sql2)->execute()->fetch();
            //pr($rows2);
            $sqlu = "UPDATE {$this->table} SET {$this->colsubnodecount}={$rows2["q"]} WHERE {$this->key}={$row[$this->key]}";
            $this->adapter->prepare($sqlu)->execute();
        }
        //die();
    }

    function generateList($id = null, $conditions = array(), $levels = 2) {
        $init = null;
        $ret = array();
        if ($id === null) {
            $conditions[] = "`{$this->parent}`=0";
        } else {
            $conditions[] = "`{$this->key}`='{$id}'";
        }

        $sql = "SELECT `{$this->key}`, $this->colname FROM {$this->table} WHERE " . implode(" AND ", $conditions);
        //echo $sql;
        $rows = $this->adapter->prepare($sql)->execute()->fetchAll();

        foreach ($rows as $row)
        {
            if ($levels > 0) {
                //pr($row);
                //$childs = $this->findChild($row[$this->key], $levels, true);
                //$childs = $this->generateList($row[$this->key], $conditions, $levels);
                //pr($childs);

                foreach ($childs as $id => $child) {
                    if ($init === null) {
                        $init = $child[$this->level];
                    }
                    $diff = $child[$this->level] - $init;
                    $ret[$id] = str_repeat("--", $diff) . " " . $child[$this->colname];
                }
            }
        }

        return $ret;
    }

    public function getChildIds($id) {
        $returnValue = array();

        $sql = "SELECT lft, rgt FROM `{$this->table}` WHERE `{$this->key}`={$id}";
        //echo $sql;
        $r = $this->_query($sql);
        $row = $db->fetch_assoc($r);

        $sql = "SELECT `{$this->key}` FROM `{$this->table}` WHERE lft >= {$row['lft']} AND rgt <= {$row['rgt']}";
        ///echo $sql;
        $r = $this->_query($sql);

        while ($row = $db->fetch_assoc($r)) {
            $returnValue[$row[$this->key]] = $row[$this->key];
        }

        return $returnValue;
    }

    public function sections() {
        //$mc = startModel('multicategory');
        $sql = "SELECT * FROM `{$this->table}` WHERE parent_id='0' ORDER BY section ASC";
        $r = $this->_query($sql);

        if ($r == false) {
            echo $sql;
            echo $db->error();
        }

        while ($row = $db->fetch_assoc($r)) {
            $sections[$row['section']] = $row[$this->colname];
        }

        //pr($sections);
        return $sections;
    }

    public function addSection($name) {
        $sections = $this->sections();

        foreach ($sections as $k => $v) {
            // Nada. Solo buscamos el ultimo indice de k para sumarle 1.
            $k++;
        }

        $this->section($k);
        $this->addNode($name, 0);
    }*/
}
