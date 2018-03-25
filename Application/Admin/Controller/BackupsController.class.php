<?php
namespace Admin\Controller;

use Think\Controller;

/**
 * Class OrderController
 * @package Admin\Controller
 */
class BackupsController extends AdminBasicController
{
    public $dir = "./Uploads/mysql/";

    public function _initialize()
    {

    }

    /**
     * 数据备份
     */
    public function backups()
    {
        vendor("Backups.Backups");
        $mysql = new \Backups(C('DB_HOST'), C('DB_USER'), C('DB_PWD'), C('DB_NAME'));
        if (!isset($_POST["backups"])) {
            $tables = $mysql->tables_list_count();
            $this->assign('tables', $tables);
            $this->display("Backups");
        } else {
            !empty($_POST["table"]) or exit($this->error("请选择要备份的表"));
            set_time_limit(300);
            $uniqid = uniqid();
            $dir = $this->dir . $uniqid;
            mkdir($this->dir, 0777, true);
            $mysql->beifen($dir, $_POST["table"], $_POST["size"]);
            echo "<script>location.href='" . U("Backups/backupsList") . "'</script>";
        }
    }

    /**
     * 备份列表
     */
    public function backupsList()
    {
        $tree = dir_read($this->dir);
        krsort($tree);
        $this->assign("dir", $this->dir);
        $this->assign("tree", $tree);
        $this->display("Backups/BackupsList");
    }

    public function delete()
    {
        if (!isset($_POST["dirname"])) {
            $dir = $_GET["dirname"];
            dir_delete($this->dir . $dir) ? $this->success("删除成功", U("Backups/backupsList")) : $this->error("删除错误");
        } else {
            foreach ($_POST["dirname"] as $dir) {
                dir_delete($this->dir . $dir) or $this->error("删除错误");
            }
            $this->success("删除成功", U("Backups/backupsList"));
        }
    }

    public function down()
    {
        $dir = $this->dir;
        $name = $_GET["dirname"];
        chdir($dir);
        $filename = "Backups" . date("YmdHis");
        yasuo($name, $filename);
        file_down($filename . ".zip");
        unlink($filename . ".zip");
    }
}