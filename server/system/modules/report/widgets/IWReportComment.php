<?php

class IWReportComment extends IWComment
{
    const COMMENT_LIST_VIEW = "application.modules.report.views.comment.loadComment";

    public function init()
    {
        $var["loadmore"] = EnvUtil::getRequest("loadmore");
        $var["inAjax"] = intval(EnvUtil::getRequest("inajax"));
        $var["module"] = $this->getModule();
        $var["assetUrl"] = Ibos::app()->assetManager->getAssetsUrl("message");
        $var["getUrl"] = Ibos::app()->urlManager->createUrl("report/comment/getcommentlist");
        $var["addUrl"] = Ibos::app()->urlManager->createUrl("report/comment/addcomment");
        $var["delUrl"] = Ibos::app()->urlManager->createUrl("report/comment/delcomment");
        $this->setParseView("comment", self::COMMENT_LIST_VIEW);
        $this->setAttributes($var);
    }

    public function run()
    {
        $attr = $this->getAttributes();
        $map = array("module" => $this->getModule(), "table" => $this->getTable(), "rowid" => $attr["rowid"], "isdel" => 0);
        $attr["count"] = Comment::model()->countByAttributes($map);
        $list = $this->getCommentList();
        $isAdministrator = Ibos::app()->user->isadministrator;
        $uid = Ibos::app()->user->uid;

        foreach ($list as &$cm) {
            $cm["isCommentDel"] = $isAdministrator || ($uid === $cm["uid"]);
            $cm["replys"] = intval(Comment::model()->countByAttributes(array("module" => "message", "table" => "comment", "rowid" => $cm["cid"], "isdel" => 0)));
        }

        $attr["comments"] = $list;
        $attr["lang"] = Ibos::getLangSources(array("message.default"));
        $content = $this->render($this->getParseView("comment"), $attr, true);
        $ajax = $attr["inAjax"];
        $count = $attr["count"];
        unset($attr);
        $return = array("isSuccess" => true, "data" => $content, "count" => $count);

        if ($ajax == 1) {
            $this->getOwner()->ajaxReturn($return);
        } else {
            echo $return["data"];
        }
    }

    public function fetchCommentList()
    {
        $type = $this->getAttributes("type");
        $this->setAttributes(array("inAjax" => 1, "loadmore" => EnvUtil::getRequest("loadmore")));

        if ($type == "reply") {
            $this->setParseView("comment", self::REPLY_LIST_VIEW);
        } else {
            $this->setParseView("comment", self::COMMENT_LIST_VIEW);
        }

        return $this->run();
    }

    protected function afterAdd($data, $sourceInfo)
    {
        if (isset($data["type"])) {
            if ($data["type"] == "reply") {
                $this->setParseView("comment", self::REPLY_PARSE_VIEW, "parse");
            } else {
                $this->setParseView("comment", self::COMMENT_PARSE_VIEW, "parse");
            }
        }

        if (isset($data["stamp"])) {
            $repid = $sourceInfo["repid"];
            $allStamp = Stamp::model()->fetchAll(array("select" => "id"));
            $stampArr = ConvertUtil::getSubByKey($allStamp, "id");
            $stamp = (in_array($data["stamp"], $stampArr) ? intval($data["stamp"]) : 0);

            if ($stamp == 0) {
                Report::model()->modify($repid, array("isreview" => 1));
            } else {
                Report::model()->modify($repid, array("isreview" => 1, "stamp" => $stamp));
                $uid = Report::model()->fetchUidByRepId($repid);
                ReportStats::model()->scoreReport($repid, $uid, $stamp);
            }
        }
    }
}
