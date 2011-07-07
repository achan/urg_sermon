<?php
class PastorFeedComponent extends Object {
    var $controller = null;
    var $settings = null;

    function initialize(&$controller, $settings = array()) {
        $this->controller =& $controller;
        $this->settings = $settings;
    }

    function build($widget_id) {
        $settings = $this->settings[$widget_id];
        $feed = $this->get_pastor_feed(isset($settings["pastor_id"]) ? $settings["pastor_id"] : null);
        $pastor = $this->controller->Group->findById($settings["pastor_id"]);
        $this->controller->set("pastor_feed_$widget_id", $feed);
        $this->controller->set("pastor_$widget_id", $pastor);
    }

    function bindModels() {
        $this->controller->loadModel("Sermon");
        $this->controller->Sermon->bindModel(array(
                "belongsTo" => array('Post' => array('className' => 'UrgPost.Post',
                                                     'foreignKey' => 'post_id',
                                                     'conditions' => '',
                                                     'fields' => '',
                                                     'order' => ''),
                                     'Series' => array('className' => 'Urg.Group',
                                                       'foreignKey' => 'series_id'),
                                     'Pastor' => array('className' => 'Urg.Group',
                                                       'foreignKey' => 'pastor_id')
        )));
    }

    function get_article_group($pastor) {
        return $this->controller->Group->find("first", array("conditions" => 
                array("Group.name" => "Articles",
                      "Group.parent_id" => $pastor["Group"]["id"])));
    }

    function get_pastor_feed($pastor_id) {
        $pastor = $this->controller->Group->findById($pastor_id);
        $this->bindModels();
        $this->controller->Sermon->bindModel(array("belongsTo" => array("Post")));
        $sermons = $this->controller->Sermon->find('all', 
                array("conditions" => array("Sermon.pastor_id" => $pastor["Group"]["id"],
                                            "Post.publish_timestamp < NOW()"),
                      "limit" => 10,
                      "order" => "Post.publish_timestamp DESC"));
        $activity = array();

        $unsorted_activity = array();
        foreach ($sermons as $sermon) {
            $unsorted_activity[$sermon["Post"]["publish_timestamp"]] = $sermon;
        }

        $article_group = $this->get_article_group($pastor);
        CakeLog::write("debug", "article group for pastor: " . Debugger::exportVar($article_group, 3));
        $this->controller->loadModel("Post");
        $this->controller->Post->bindModel(array("belongsTo" => array("Group")));
        $this->controller->Post->bindModel(array("hasMany" => array("Attachment")));
        
        $articles = $this->controller->Post->find("all",
                array("conditions" => array("Post.group_id" => $article_group["Group"]["id"],
                                            "Post.publish_timestamp < NOW()"),
                      "limit" => 10,
                      "order" => "Post.publish_timestamp DESC"));

        foreach ($articles as $article) {
            $unsorted_activity[$article["Post"]["publish_timestamp"]] = $article;
        }

        krsort($unsorted_activity);

        $activity = array_values($unsorted_activity);

        CakeLog::write("debug", "pastor activity: " . Debugger::exportVar($activity, 3));

        return $activity;
    }
}