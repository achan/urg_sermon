<?php
App::import("Sanitize");
App::import("Component", "Cuploadify.Cuploadify");
App::import("Component", "ImgLib.ImgLib");
App::import("Component", "Bible.Bible");
App::import("Helper", "Bible.Bible");
App::import("Helper", "Sm2.SoundManager2");
class SermonsController extends UrgSermonAppController {
    var $AUDIO_WEBROOT = "audio";
    var $IMAGES_WEBROOT = "img";
    var $FILES_WEBROOT = "files";

    var $AUDIO = "/app/plugins/urg_sermon/webroot/audio";
    var $IMAGES = "/app/plugins/urg_sermon/webroot/img";
    var $FILES = "/app/plugins/urg_sermon/webroot/files";

    var $BANNER_SIZE = 700;
    var $PANEL_BANNER_SIZE = 460;
    
    var $components = array(
           "Auth" => array(
                   "loginAction" => array(
                           "plugin" => "urg",
                           "controller" => "users",
                           "action" => "login",
                           "admin" => false
                   )
           ), "Urg", "Cuploadify", "ImgLib", 
           "Bible" => array("Esv"=>array("key" => "bef9e04393f0f17f"))
    );

    var $helpers = array(
        "Js" => array("Jquery"), "Time", "Bible", "SoundManager2"
    );
    var $name = 'Sermons';

    function beforeFilter() {
        $this->Auth->allow("view", "passages");
    }

    function index() {
        $this->Sermon->recursive = 0;
        $this->set('sermons', $this->paginate());
    }

    function view($id = null) {
        $this->log("Entering view action", LOG_DEBUG);
        if (!$id) {
            $this->Session->setFlash(__('Invalid sermon', true));
            $this->redirect(array('action' => 'index'));
        }
        $sermon = $this->Sermon->find("first", 
                array(  "conditions" => array("Sermon.id"=>$id),
                        "recursive" => 3
                )
        );

        $series = $this->Sermon->find("all",
                array(  "conditions" => array("Sermon.series_id" => $sermon["Series"]["id"]),
                        "order" => array("Post.publish_timestamp")
                )
        );
                        

        $this->loadModel("Attachment");
        $this->Attachment->bindModel(array("belongsTo" => array("AttachmentType")));
        $attachments = $this->Attachment->find("list", 
                array(  "conditions" => array("AND" => array(
                                "AttachmentType.name" => array("Banner", "Audio", "Documents"),
                                "Attachment.post_id" => $sermon["Post"]["id"])
                        ),
                        "fields" => array(  "Attachment.filename", 
                                            "Attachment.id",
                                            "AttachmentType.name"
                                    ),
                        "joins" => array(   
                                array(  "table" => "attachment_types",
                                        "alias" => "AttachmentType",
                                        "type" => "LEFT",
                                        "conditions" => array(
                                            "AttachmentType.id = Attachment.attachment_type_id"
                                        )
                                )
                        )
               )
        );
        $this->log("Viewing sermon: " . Debugger::exportVar($sermon, 3), 
                LOG_DEBUG);
        $this->log("Available attachments: " . Debugger::exportVar($attachments, 1), 
                LOG_DEBUG);
        $this->log("Related sermons: " . Debugger::exportVar($series, 3), LOG_DEBUG);
        $this->set('sermon', $sermon);
        $this->set("attachments", $attachments);
        $this->set("series_sermons", $series);

        $banner = null; 
        foreach ($attachments["Banner"] as $key=>$attachment_id) {
            $banner = $key;
        }

        $this->set("banners", array($this->get_image_path($banner, $sermon, $this->BANNER_SIZE)));
    }

    function get_image_path($filename, $sermon, $width, $height = 0) {
        $full_image_path = $this->get_doc_root($this->IMAGES) . "/" .  $sermon["Sermon"]["id"];
        $image = $this->ImgLib->get_image("$full_image_path/$filename", $width, $height, 'landscape'); 
        return "/urg_sermon/img/" . $sermon["Sermon"]["id"] . "/" . $image["filename"];
    }

    function passages($passage) {
        $this->layout = "ajax";
        $passages = $this->Bible->get_passage($passage);

        if ($passages == null) {
            $this->log("No passages found for $passage", LOG_DEBUG);
            $this->Session->setFlash(
                    __("We're having problems retrieving Bible verses at the moment. Please try again later.", 
                       true), 
                    "flash_error");
        }

        $this->set("passages", $passages);
    }

    function populate_series() {
        if ($this->data["Sermon"]["series_name"] != "") {
            $series_name = $this->data["Sermon"]["series_name"];
            $series_group = $this->Sermon->Series->findByName("Series");
            $existing_series = $this->Sermon->Series->find("first", 
                    array("conditions" => 
                            array(
                                    "Series.group_id" => $series_group["Series"]["id"], 
                                    "Series.name" => $series_name
                            )
                    )
            );

            if ($existing_series === false) {
                $this->Sermon->Series->create();
                $this->data["Series"]["group_id"] = $series_group["Series"]["id"];
                $this->data["Series"]["name"] = $this->data["Sermon"]["series_name"];
                $this->log("New Series for: " . $series_name, LOG_DEBUG);
            } else {
                $this->data["Series"] = $existing_series["Series"];
                $this->log("Series exists: " . Debugger::exportVar($this->data["Series"], 3), 
                        LOG_DEBUG);
            }
        } else {
            $this->log("No series to populate...", LOG_DEBUG);
        }
    }

    function prepare_attachments() {
        $logged_user = $this->Auth->user();
        $attachment_count = isset($this->data["Attachment"]) ? 
                sizeof($this->data["Attachment"]) : 0;
        if ($attachment_count > 0) {
            $this->log("preparing $attachment_count attachments...", LOG_DEBUG);
            foreach ($this->data["Attachment"] as &$attachment) {
                $attachment["user_id"] = $logged_user["User"]["id"];
            }

            $this->Sermon->Post->bindModel(array("hasMany" => array("Attachment")));
            unset($this->Sermon->Post->Attachment->validate["post_id"]);
        }
    }

    function save_post($id = null) {
        $this->Sermon->Post->id = $id;

        $logged_user = $this->Auth->user();
        $this->data["User"] = $logged_user["User"];

        $this->populate_series();
        $this->prepare_attachments();

        $this->log("post belongsto: " . 
                Debugger::exportVar($this->Sermon->Post->belongsTo, 3), LOG_DEBUG);

        unset($this->Sermon->Post->validate["group_id"]);

        $this->data["Group"] = $this->data["Series"];

        $this->log("Saving post: " . Debugger::exportVar($this->data, 3), LOG_DEBUG);

        $this->log("updated post belongsto: " . 
                Debugger::exportVar($this->Sermon->Post->belongsTo, 3), LOG_DEBUG);
        $status = $this->Sermon->Post->saveAll($this->data, array("atomic"=>false));
        unset($this->data["Group"]);

        if (isset($this->Sermon->Post->Group->id)) {
            $this->data["Series"]["id"] = $this->Sermon->Post->Group->id;
            $this->log("Saving post with group id: " . $this->data["Series"]["id"], LOG_DEBUG);
        }

        $this->log("Post saved: " . Debugger::exportVar($status, 3), LOG_DEBUG);

        return $status;
    }

    function populate_speaker() {
        if ($this->data["Sermon"]["speaker_name"] != "") {
            $speaker_name = $this->data["Sermon"]["speaker_name"];
            $pastors_group = $this->Sermon->Pastor->findByName("Pastors");
            $existing_pastor = $this->Sermon->Pastor->find("first", 
                    array("conditions" => 
                            array(
                                    "Pastor.group_id" => $pastors_group["Pastor"]["id"], 
                                    "Pastor.name" => $speaker_name
                            )
                    )
            );

            if ($existing_pastor === false) {
                $this->log("New speaker: " . $speaker_name, LOG_DEBUG);
            } else {
                $this->data["Pastor"] = $existing_pastor["Pastor"];
                $this->data["Sermon"]["speaker_name"] = null;
                unset($this->Sermon->validate["speaker_name"]);
                $this->log("Speaker is a pastor: $speaker_name", LOG_DEBUG);
            }
        }
    }

    function consolidate_attachments($webroot_dirs, $temp_dir) {
        $doc_root = $this->remove_trailing_slash(env("DOCUMENT_ROOT"));

        if (!is_array($webroot_dirs)) {
            $webroot_dirs = array($webroot_dirs);
        }

        foreach ($webroot_dirs as $webroot_dir) {
            $temp_webroot = "$webroot_dir/$temp_dir";

            if (file_exists($doc_root . $temp_webroot)) {
                $perm_dir = $webroot_dir . "/" . $this->Sermon->id;
                $this->rename_dir($doc_root . $temp_webroot, $doc_root . $perm_dir);
                $this->log("moved attachments to permanent folder: $doc_root$perm_dir", LOG_DEBUG);
            } else {
                $this->log("no attachments to move, since folder doesn't exist: $doc_root$temp_webroot",
                        LOG_DEBUG);
            }
        }
    }

    function resize_banner($sermon_id) {
        $full_image_path = $this->get_doc_root($this->IMAGES) . "/" .  $sermon_id;

        if (file_exists($full_image_path)) {
            $this->loadModel("Attachment");
            $this->Attachment->bindModel(array("belongsTo" => array("AttachmentType")));

            $banner_type = $this->Attachment->AttachmentType->findByName("Banner");
            $post_banner = $this->Attachment->find("first", 
                    array("conditions" => array("AND" => array(
                    "Attachment.attachment_type_id" => $banner_type["AttachmentType"]["id"],
                    "Attachment.post_id" => $this->data["Post"]["id"]
            ))));

            if (isset($post_banner["Attachment"])) {
                $this->log("post banner: " . Debugger::exportVar($post_banner, 3), LOG_DEBUG);
                $this->log("resizing banners...", LOG_DEBUG);
                $this->log("full sermon image path: $full_image_path", LOG_DEBUG);
                $saved_image = $this->ImgLib->get_image($full_image_path . "/" . 
                        $post_banner["Attachment"]["filename"], $this->BANNER_SIZE, 0, 'landscape');
                $this->log("saved $saved_image[filename]", LOG_DEBUG);
            } else {
                $this->log("no banners found for post: " . $this->data["Post"]["id"], LOG_DEBUG);
            }
        }
    }

    function add() {
        if (!empty($this->data)) {
            $logged_user = $this->Auth->user();

            $sermon_ds = $this->Sermon->getDataSource();
            $post_ds = $this->Sermon->Post->getDataSource();

            $post_ds->begin($this->Sermon->Post);
            $sermon_ds->begin($this->Sermon);

            $this->Sermon->Post->create();
            $this->Sermon->Post->bindModel(array("belongsTo" => array(
                    "Series" => array(
                        "className" => "Urg.Group",
                        "foreignKey" => "group_id"
                    )
                )
            ));
            $save_post_status = $this->save_post();

            // if post saved successfully
            if (!is_bool($save_post_status) || $save_post_status) {
                $this->data["Series"]["id"] = $this->Sermon->Series->id;
                $this->data["Post"]["id"] = $this->Sermon->Post->id;
                $this->log("Post successfully saved. Now saving sermon with series id as: " . 
                        $this->data["Series"]["id"] . " group id as: " .
                        $this->data["Post"]["id"], LOG_DEBUG);
                $this->Sermon->create();

                $this->populate_speaker();

                $this->log("Attempting to save: " . Debugger::exportVar($this->data, 3), LOG_DEBUG);
                if ($this->Sermon->saveAll($this->data, array("atomic"=>false))) {
                    $temp_dir = $this->data["Sermon"]["uuid"];

                    $this->consolidate_attachments(
                            array($this->AUDIO, $this->FILES, $this->IMAGES), 
                            $temp_dir
                    );

                    $this->resize_banner($this->Sermon->id);

                    $post_ds->commit($this->Sermon->Post);
                    $sermon_ds->commit($this->Sermon);

                    $this->log("Sermon successfully saved.", LOG_DEBUG);
                    $this->Session->setFlash(__('The sermon has been saved', true));
                    $this->redirect(array('action' => 'index'));
                } else {
                    $sermon_ds->rollback($this->Sermon);
                    $post_ds->rollback($this->Sermon->Post);
                    $this->log("Sermon needs to be corrected, redirecting to form.", LOG_DEBUG);
                    $this->Session->setFlash(
                            __('The sermon could not be saved. Please, try again.', true));
                } 
            } else {
                $this->Sermon->saveAll($this->data, array("validate"=>"only"));
                $this->log("Sermon needs to be corrected, redirecting to form.", LOG_DEBUG);
                $this->Session->setFlash(__('The sermon could not be saved. Please, try again.', true));
            }
        } else {
            $this->data["Sermon"]["uuid"] = String::uuid();
        }

        $this->loadModel("Attachment");
        $this->Attachment->bindModel(array("belongsTo" => array("AttachmentType")));

        $this->set("banner_type", 
                $this->Attachment->AttachmentType->findByName("Banner"));
        $this->set("audio_type", 
                $this->Attachment->AttachmentType->findByName("Audio"));

        $posts = $this->Sermon->Post->find('list');
        $this->set(compact('posts'));
    }

    function edit($id = null) {
        $this->Sermon->id = $id;

        if (!$id && empty($this->data)) {
            $this->Session->setFlash(__('Invalid sermon', true));
            $this->redirect(array('action' => 'index'));
        }
        if (!empty($this->data)) {
            $logged_user = $this->Auth->user();

            $sermon_ds = $this->Sermon->getDataSource();
            $post_ds = $this->Sermon->Post->getDataSource();

            $post_ds->begin($this->Sermon->Post);
            $sermon_ds->begin($this->Sermon);

            $save_post_status = $this->save_post($this->data["Post"]["id"]);

            // if post saved successfully
            if (!is_bool($save_post_status) || $save_post_status) {
                $this->log("Post successfully saved. Now saving sermon with series id as: " . 
                        $this->data["Series"]["id"] . " and post id as: " . 
                        $this->data["Post"]["id"], LOG_DEBUG);

                $this->populate_speaker();

                $this->log("Attempting to save: " . Debugger::exportVar($this->data, 3), LOG_DEBUG);
                if ($this->Sermon->saveAll($this->data, array("atomic"=>false))) {
                    $this->resize_banner($this->Sermon->id);

                    $post_ds->commit($this->Sermon->Post);
                    $sermon_ds->commit($this->Sermon);

                    $this->log("Sermon successfully saved.", LOG_DEBUG);
                    $this->Session->setFlash(__('The sermon has been saved', true));
                    $this->redirect(array('action' => 'index'));
                } else {
                    $sermon_ds->rollback($this->Sermon);
                    $post_ds->rollback($this->Sermon->Post);
                    $this->log("Sermon needs to be corrected, redirecting to form.", LOG_DEBUG);
                    $this->Session->setFlash(
                            __('The sermon could not be saved. Please, try again.', true));
                } 
            } else {
                $this->Sermon->saveAll($this->data, array("validate"=>"only"));
                $this->log("Sermon needs to be corrected, redirecting to form.", LOG_DEBUG);
                $this->Session->setFlash(__('The sermon could not be saved. Please, try again.', true));
            }
        }

        if (empty($this->data)) {
            $this->data = $this->Sermon->read(null, $id);
        }

        $this->loadModel("Attachment");
        $this->Attachment->bindModel(array("belongsTo" => array("AttachmentType")));

        $banner_type = $this->Attachment->AttachmentType->findByName("Banner");

        $this->set("banner_type", $banner_type);
        $this->set("audio_type", $this->Attachment->AttachmentType->findByName("Audio"));
        
        $posts = $this->Sermon->Post->find('list');
        $this->data["Sermon"]["series_name"] = $this->data["Series"]["name"];
        $banner = $this->Attachment->find("first", array(
                "conditions"=>
                        array("Attachment.post_id"=>$this->data["Post"]["id"],
                              "Attachment.attachment_type_id"=>$banner_type["AttachmentType"]["id"]
                        ),
                "order" => "Attachment.created DESC"
            )
        );

        $this->set("banner", $this->get_image_path($banner["Attachment"]["filename"], 
                                                   $this->data, 
                                                   $this->PANEL_BANNER_SIZE));

        $this->set("attachments", $this->Attachment->find("all", array("conditions"=>
                array("Attachment.post_id"=>$this->data["Post"]["id"],
                      "Attachment.attachment_type_id !="=>$banner_type["AttachmentType"]["id"]
                )
            )
        ));

        $this->log("sermon banner: " . Debugger::exportVar($banner, 2), LOG_DEBUG);
        $this->set(compact('posts'));
    }

    function delete($id = null) {
        if (!$id) {
            $this->Session->setFlash(__('Invalid id for sermon', true));
            $this->redirect(array('action'=>'index'));
        }
        if ($this->Sermon->delete($id)) {
            $this->Session->setFlash(__('Sermon deleted', true));
            $this->redirect(array('action'=>'index'));
        }
        $this->Session->setFlash(__('Sermon was not deleted', true));
        $this->redirect(array('action' => 'index'));
    }

    function autocomplete_speaker() {
        $term = Sanitize::clean($this->params["url"]["term"]);
        $matches = strlen($term) == 0 ? $this->suggest_speaker() : $this->search_speaker($term);
        $this->set("data",$matches);
        $this->render("json", "ajax");
    }
    
    function search_speaker($term) {
        $prepared_matches = array();

        $pastors = $this->requestAction("/urg_sermon/pastors/search/" . $this->params["url"]["term"]);
        foreach ($pastors as $pastor) {
            array_push($prepared_matches,
                    array("label"=> $pastor["Group"]["name"], "belongsToChurch"=>true,
                            "value"=>$pastor["Group"]["name"], "group_id"=>$pastor["Group"]["id"]));
        }

        $matches = $this->Sermon->query("SELECT DISTINCT speaker_name speaker_name " .
                "FROM sermons Sermon WHERE speaker_name LIKE '%$term%' ORDER BY speaker_name LIMIT 3");
        foreach ($matches as $match) {
            array_push($prepared_matches, 
                    array("label"=>$match["Sermon"]["speaker_name"], 
                            "value"=>$match["Sermon"]["speaker_name"]));
        }

        return $prepared_matches;
    }

    function suggest_speaker() {
        $prepared_matches = array();

        $pastors = $this->requestAction("/urg_sermon/pastors/search/" . $this->params["url"]["term"]);
        foreach ($pastors as $pastor) {
            array_push($prepared_matches,
                    array("label"=> $pastor["Group"]["name"], "belongsToChurch"=>true,
                            "value"=>$pastor["Group"]["name"], "group_id"=>$pastor["Group"]["id"]));
        }
        
        return $prepared_matches;
    }

    function upload_attachments() {
        $this->log("uploading attachments...", LOG_DEBUG);

        $this->log("determining what type of attachment...", LOG_DEBUG);

        $this->loadModel("Attachment");
        $this->Attachment->bindModel(array("belongsTo" => array("AttachmentType")));
        $attachment_type = null;
        $root = null;
        if ($this->is_filetype($this->Cuploadify->get_filename(),
                array(".jpg", ".jpeg", ".png", ".gif", ".bmp"))) {
            $root = $this->IMAGES;
            $attachment_type = $this->Attachment->AttachmentType->findByName("Images");
            $webroot_folder = $this->IMAGES_WEBROOT;
        } else if ($this->is_filetype($this->Cuploadify->get_filename(), array(".mp3"))) {
            $root = $this->AUDIO;
            $attachment_type = $this->Attachment->AttachmentType->findByName("Audio");
            $webroot_folder = $this->AUDIO_WEBROOT;
        } else if ($this->is_filetype($this->Cuploadify->get_filename(), 
                array(".ppt", ".pptx", ".doc", ".docx"))) {
            $root = $this->FILES;
            $attachment_type = $this->Attachment->AttachmentType->findByName("Documents");
            $webroot_folder = $this->FILES_WEBROOT;
        }

        $webroot_folder = $this->get_webroot_folder($this->Cuploadify->get_filename());
        $this->log("attachment type detected as: " . Debugger::exportVar($attachment_type, 3), 
                LOG_DEBUG);
        $this->upload($root);

        //TODO cache id
        $this->set("data", array(
                "attachment_type_id"=>$attachment_type["AttachmentType"]["id"],
                "webroot_folder"=>$webroot_folder
        ));
        $this->render("json", "ajax");
    }

    function get_webroot_folder($filename) {
        $webroot_folder = null;

        if ($this->is_filetype($filename, array(".jpg", ".jpeg", ".png", ".gif", ".bmp"))) {
            $webroot_folder = $this->IMAGES_WEBROOT;
        } else if ($this->is_filetype($filename, array(".mp3"))) {
            $webroot_folder = $this->AUDIO_WEBROOT;
        } else if ($this->is_filetype($filename, array(".ppt", ".pptx", ".doc", ".docx"))) {
            $webroot_folder = $this->FILES_WEBROOT;
        }

        return $webroot_folder;
    }

    function upload($root) {
        $options = array("root" => $root);
        $this->log("uploading options: " . Debugger::exportVar($options), LOG_DEBUG);
        $this->Cuploadify->upload($options);
        $this->log("done uploading.", LOG_DEBUG);
    }

    function upload_images() {
        $this->log("uploading images...", LOG_DEBUG);
        $this->upload($this->IMAGES);
    }

    function upload_image() {
        $this->upload($this->IMAGES);
        $options = array("root" => $this->IMAGES);
        $target_folder = $this->Cuploadify->get_target_folder($options);
        $filename = $target_folder . $this->Cuploadify->get_filename();
    }

    /**
     * Validates the field specified by the parameters.
     * Returns the error message key.
     */
    function validate_field($model_name="Sermon", $field) {
        $this->layout = "ajax";
        $errors = array();

        $this->data[$model_name][$field] = $this->params["url"]["value"];

        $model = $model_name == "Sermon" ? $this->Sermon : $this->Sermon->{$model_name};
        $model->set($this->data);

        if ($model->validates(array("fieldList"=>array($field)))) {
        } else {
            $errors = $model->invalidFields();
        }

        $this->log("Errors on $model_name.$field: " . Debugger::exportVar($errors, 2), LOG_DEBUG);
        $this->set("error", isset($errors[$field]) ? $errors[$field] : null);
        $this->set("model", $model_name);
        $this->set("field", $field);
    }

    /**
     * Removes the trailing slash from the string specified.
     * @param $string the string to remove the trailing slash from.
     */
    function remove_trailing_slash($string) {
        $string_length = strlen($string);
        if (strrpos($string, "/") === $string_length - 1) {
            $string = substr($string, 0, $string_length - 1);
        }

        return $string;
    }

    /**
     * Renames the directory, even if there are contents inside of it.
     * @param $string old_dir The old directory name.
     * @param $string new_dir The new directory name.
     */
    function rename_dir($old_name, $new_name) {
        $this->log("Moving $old_name to $new_name", LOG_DEBUG);
        if (file_exists($old_name)) {
            $this->log("creating dir: $new_name", LOG_DEBUG);
            $old = umask(0);
            mkdir($new_name, 0777, true); 
            umask($old);
            if ($handle = opendir($old_name)) {
                while (false !== ($file = readdir($handle))) {
                    if ($file != "." && $file != "..") {
                       rename("$old_name/$file", "$new_name/$file"); 
                    }
                }
                closedir($handle);
                rmdir($old_name);
            }
        }
    }

    function is_filetype($filename, $filetypes) {
        $filename = strtolower($filename);
        $is = false;
        if (is_array($filetypes)) {
            foreach ($filetypes as $filetype) {
                if ($this->ends_with($filename, $filetype)) {
                    $is = true;
                    break;
                }
            }
        } else {
            $is = $this->ends_with($filename, $filetypes);
        }

        $this->log("is $filename part of " . implode(",",$filetypes) . "? " . ($is ? "true" : "false"), 
                LOG_DEBUG);
        return $is;
    }

    function ends_with($haystack, $needle) {
        return strrpos($haystack, $needle) === strlen($haystack)-strlen($needle);
    }
    
    function get_doc_root($root = null) {
        $doc_root = $this->remove_trailing_slash(env('DOCUMENT_ROOT'));

        if ($root != null) {
            $root = $this->remove_trailing_slash($root);
            $doc_root .=  $root;
        }

        return $doc_root;
    }
}
?>
