<?php
class SeriesController extends UrgSermonAppController {
    var $name = 'Series';
    var $useTable = false;
    var $uses = array("Group");

    function autocomplete() {
        $matches = null;
        $term = trim($this->params["url"]["term"]);
        $matches = strlen($term) == 0 ? $this->suggest() : $this->search($term);
        $prepared_matches = array();
        foreach ($matches as $match) {
            $results = array("label" => $match["Group"]["name"], "value" => $match["Group"]["name"]);
            array_push($prepared_matches, array_merge($match["Group"], $results));
        }
        $this->set("matches",$prepared_matches);
        $this->layout = "ajax";
    }

    function suggest() {
        $series_group = $this->Group->find("first", array("conditions" => array("Group.name" => "Series")));
        $no_series = $this->Group->find("first", array("conditions" => array("Group.name" => "No Series")));
        $suggestions = $this->Group->find("all", array("conditions" => array("Group.id !=" => $no_series["Group"]["id"], "Group.parent_id" => $series_group["Group"]["id"]), "order" => array("Group.modified DESC"), "limit" => 3));
        array_push($suggestions, $no_series);
        return $suggestions;
    }

    function search($term) {
        $series_group = $this->Group->find("first", array("conditions" => array("Group.name" => "Series")));
        return $this->Group->find("all", 
                array("conditions" => array("Group.name LIKE" => "%$term%", 
                                            "Group.parent_id" => $series_group["Group"]["id"]
                                      ),
                      "limit" => 5
                )
        );
    }

    function create($series_name) {
        $series_group = $this->Group->find("first", array("conditions" => array("Group.name" => "Series")));
        $existing_group = $this->Group->find("first", array("conditions" => array("parent_id" => $series_group["Group"]["id"], "Group.name" => $series_name)));
        $series_id = null;

        if ($existing_group === false) {
            $this->Group->create();
            $series = array();
            $series["parent_id"] = $series_group["Group"]["id"];
            $series["name"] = $series_name;

            $group = array("Group" => $series);

            $this->Group->save($group);

            $series_id = $this->Group->id;
        } else {
            $series_id = $existing_group["Group"]["id"];
        }

        return $series_id;
    }
}
?>
