<?php
/*
Plugin Name: Open Literature
Plugin URI: http://github.com/OpenHumanities
Description: A plugin to insert an Open Literature instance into Wordpress. 
Author: OKFN
Version: 0.1
Author URI: http://www.openliterature.net
*/
ini_set("allow_url_fopen", true);
include __DIR__ .'/controller/get_text_controller.php';

// set up the Textus slug API
add_action('init', 'register_textus');

//Set up the Textus API
add_action('init', 'textus_get_control');
add_shortcode('textus', 'textus_shortcode');

// function to create annotation table
register_activation_hook( __FILE__, 'textus_install' );

// Add initialization and activation hooks
//register_activation_hook("$dir/textus.php", 'textus_activation');
//register_deactivation_hook("$dir/textus.php", 'textus_deactivation');

/* Wordpress Textus functions */

/**
 * Function to create a "slug" for the Textus Javascript / HTML
 */
function register_textus()
{
    $label = array(   
            'name' => _x('Textus', 'post type general name'),
            'singular_name' => _x('Textus Item', 'post type singular name'),
            'add_new' => _x('Add New', 'textus item'),
            'add_new_item' => __('Add New Textus Item'),
            'edit_item' => __('Edit Textus Item'),
            'new_item' => __('New Textus Item'),
            'view_item' => __('View Textus Item'),
            'search_items' => __('Search Texts'),
            'not_found' =>  __('Nothing found'),
            'not_found_in_trash' => __('Nothing found in Trash'),
            'parent_item_colon' => ''
                    );
    
    $args = array(
            'labels' => $labels,
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'query_var' => true,
            'rewrite' => true,
            'capability_type' => 'post',
            'hierarchical' => false,
            'menu_position' => null,
            'supports' => array('title','editor','thumbnail')
    );
    // register the post type
    register_post_type( 'textus', $args );
    //register the rewrite rules
    add_filter('rewrite_rules_array', 'textus_rewrites');
}

/**
 * Shortcode creation function to get the correct text
 * from the store
 * 
 * @param array $atts
 * @return string
 * HTML to place the test with textus markup
 */
function textus_shortcode( $atts ) {
    //extract the id from the incoming array
    extract(
      shortcode_atts(
        array(
          'id' => '',
        ), 
      $atts)
    );
    $rawtext = "/wordpress/wp-content/uploads/".$atts['id']."-text.txt";   
    $rawjson = "/wordpress/wp-content/uploads/".$atts['id']."-typography.json";
    $notes_id = split('/',$atts['id']);

    // return the text with the call the the Javascript location
    return '<div id=\'textViewDiv\'></div>
<!-- Textus JS dependencies -->
<script src="http://okfnlabs.org/textus-viewer/vendor/jquery-1.7.2.js"></script>
<script src="http://okfnlabs.org/textus-viewer/vendor/jquery.ui-1.8.22.js"></script>
<script src="http://okfnlabs.org/textus-viewer/vendor/underscore-1.3.3.js"></script>
<script src="http://okfnlabs.org/textus-viewer/vendor/backbone-0.9.2.js"></script>
<script src="http://okfnlabs.org/textus-viewer/vendor/backbone.forms-0.10.0.js"></script>
<script src="http://okfnlabs.org/textus-viewer/vendor/bootstrap.js"></script>
<script src="http://okfnlabs.org/textus-viewer/vendor/bootstrap.modal-1.4.0.js"></script>
<script src="http://okfnlabs.org/textus-viewer/vendor/jquery.ui.colorPicker.js"></script>
<!-- Textus JS viewer -->
<script src="http://okfnlabs.org/textus-viewer/js/textus.js"></script>
<script src="http://okfnlabs.org/textus-viewer/js/model.js"></script>
<script src="http://okfnlabs.org/textus-viewer/js/annotation.js"></script>
<script src="http://okfnlabs.org/textus-viewer/js/viewer.js"></script>
<script type="text/javascript">
var currentUser = { id : '.get_current_user_id().'};
jQuery(document).ready(function() {
  // Create a Text object with some fixture data
  var text = new Textus.Model.Text({
    // id is needed if new annotations will be allowed
    id: \'text-1\',
    textUrl: "'.$rawtext.'",
    typographyUrl: "'.$rawjson.'",
    annotationsUrl: "http://localhost/wordpress/?text='.$notes_id[1].'&type=annotation"
  });
  // Load the text
  text.fetch(function(err) {
    // you could check the err if you want to be sure text has loaded ok

    // set up the viewer
    var viewer = new Textus.Viewer({
      el: $(\'.textus-viewer-here\'),
      text: text,
      router: null,
      user: {
        // example has id:\'bob\' but Wordpress uses numerics.
        id: \''.get_current_user_id().'\'
      }
    });
    // and now render it
    viewer.render();
  });
});
</script>
</pre>';
} 


/**
 * Function to get the requested text
 */
function textus_get_text($id, $type) {
    $request = new get_text_controller();
    $text = $request->ol_get_text($id, $type);

    if ($text['error']) {
        return $text['error'];
    }
    else{
        return $text['data'];
    }
}

/* Textus API */
function is_server()
{
        $server = false;
        switch($_SERVER['REQUEST_METHOD']) {
          case 'GET':
            if (isset($_GET['text']) ) {
              $server = true;
            }
            break;
          case 'POST':
          case 'PUT':
          case 'DELETE':
            //needs testing against the textus code
            $server = true;
            break;
          default:
            wp_send_json(array("error" =>"Method not supported"));
            break;
        }
        return $server;
}

/**
* Registered function which acts as an API for the textus viewer
* @param the GET url
*  Looks for the text and type parameters
*  @todo check if the values are always ints from the Textus
*
*/
function textus_get_control()
{
        global $urllink;

        if (is_server()) {
                
         // Load the relevant controller that contains the methods/
         
         switch($_SERVER['REQUEST_METHOD']) {
           case 'GET':
               $request = new get_text_controller();
               //$parse = parse_parameters();
               if ( $_GET['type'] == 'annotation' ) {
                  if (intval($_GET['text'])) {
                     return_response(textus_get_annotations($_GET['text']));
                     #return_response(array("status"=>200, "notes"=>textus_get_annotations($_GET['text'])));
                  } else {
                     return_response(array("status" => 403, "error"=>"You need to specify a text"));
                  }
                }
                break;
            case 'POST':
              $textid = json_decode(file_get_contents("php://input"), TRUE);
              if (isset($textid['textid'])) {
                if ( ! is_user_logged_in()) {
                  return_response(array("status" => 403, "note"=>"This user is not logged in"));
                } else {
		      $current_user = wp_get_current_user();
		      $noteid = textus_insert_annotation(
		        $current_user->ID, $textid['textid'], 
		        $textid['start'], $textid['end'], 
		        $textid['private'], 
		        $textid['payload']['language'], $textid['payload']['text']
		      );

		      if (intval($noteid) > 0) {
		         return_response(array("status" => 200, "note"=>"The note has been stored" + intval($noteid)));
		      } else {
		         return_response(array("status" => 403, "note"=>"The note could not updated"));
		      }
		      
		      break;
                }
              } else {
                 break;
              }
            case 'PUT':
              $textid = json_decode(file_get_contents("php://input"), TRUE);
               print "PUT";
              if (isset($textid['textid'])) {
		      // returns the new noteid
               if ( ! is_user_logged_in()) {
                 return_response(array("status" => 403, "note"=>"This user is not logged in"));
               } else {
                      $current_user = wp_get_current_user();
		      $noteid = textus_updates_annotation(
		        $current_user->ID, $textid['textid'], 
		        $textid['start'], $textid['end'], 
		        $textid['private'], 
		        $textid['payload']['language'], $textid['payload']['text'], $textid['id']);
		                    
		      if (intval($noteid) > 0 ) {
		          return_response(array("status"=> 200, "notes" => $textid['id'] + " has been updated"));
		      }
		      break;
                 }
              } else {
                 break;
              }
            case 'DELETE':
             
              //@todo get the vars which the textus viewer sets
              $textid = json_decode(file_get_contents("php://input"), TRUE);
              if ( ! is_user_logged_in()) {
                 return_response(array("status" => 403, "note"=>"This user is not logged in"));
              } else {
                      $current_user = wp_get_current_user();
		      $noteid = textus_delete_annotation($textid['id']);
		      if (intval($noteid) > 0 ) {
		          return_response(array("status"=> 200, "notes" => $textid['id'] + " has been deleted"));
		      }
		      break;
                }

           default:
             $parse = parse_parameters();
             if ($parse['action'] == 'json') {
                 return wp_send_json( array ('error' => 'Method is unsupported') );
             }
             break;
        }
   }
}

/**
* Function to parse the parameters.
* If the request method is get, then use the parse_str() to parse them
*
* Else take the input stream
*/
function parse_parameters($data)
{
        $parameters = array();
        $body_params = array();
        //if we get a GET, then parse the query string
        if($_SERVER['REQUEST_METHOD'] == 'GET') {
          if (isset($_SERVER['QUERY_STRING'])) {
            // make this more defensive
             return $_GET;
          }
        } else {
                // Otherwise it is POST, PUT or DELETE.
                // At the moment, we only deal with JSON
                //$data = file_get_contents("php://input");
                $body_params = json_decode($data, TRUE);
                print_r($body_params);
        }

        foreach ($body_params as $field => $value) {
                $parameters[$field]= $value;
        }
        return $parameters;
}

/**
* Function to return the response given by the controller.
*
* @param Array $response_data
* Array of the data returned by the system
* @return String
* Response string depending on request type - JSON or HTML
*/
function return_response ($response_data) {
        // If the format is JSON, then send JSON else load the correct template
        //if ($response_data['format'] == 'json') {
         if (array_key_exists('error', $response_data)) {
                 return wp_send_json($response_data);
         }
         else {
           return wp_send_json($response_data);
         }
}

/* Wordpress DB functions */

/**
*  Functions to install a table for the annotations in Wordpress
*
*/
function textus_install() {
   global $wpdb;
   // name it as like the other WP tables but add textus so it can be quickly found
    $table_name = $wpdb->prefix . "textus_annotations"; 
	/*
	"start" : 300,
	"end" : 320,
	"type" : "textus:comment",
	"userid" : [wordpress-id]
	"private": false
	"date" : "2010-10-28T12:34Z",
	"payload" : {
	    "lang" : "en"
	    "text" : "Those twenty characters really blow me away, man..."
	} 

       id will be int of the currently logged in user. 
*/
   $sql = "CREATE TABLE $table_name (
     id mediumint(9) NOT NULL AUTO_INCREMENT,
     textid mediumint(9) NOT NULL, 
     time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
     start smallint NOT NULL,
     end  smallint NOT NULL,
     userid  smallint NOT NULL,
     private tinytext NOT NULL,
     language tinytext NOT NULL, 
     text text NOT NULL,
     UNIQUE KEY id (id)
   );";

   require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
  dbDelta( $sql );

}

/**
*  Function to insert the notes into the store
*
*  @return int
*  Number of rows affected. If 0, then operation has failed
*/
function textus_insert_annotation($userid, $textid, $start, $end, $private, $lang, $text) {
  $rows = textus_db_insert_annotation($userid, $textid, $start, $end, $private, $lang, $text);
  return ($rows) ? $rows : false;
 
}

/**
*  Function to update the notes into the store
*
*  @return int
*  Number of rows affected. If 0, then operation has failed
*/
function textus_updates_annotation($userid, $id, $start, $end, $private, $lang, $text, $noteid) {
  $rows = textus_db_update_annotation($userid, $id, $start, $end, $private, $lang, $text, $noteid);
  if ($rows)
  {
    return $rows;
  }
}

/**
*  Function to update the notes into the store
*
*  @return int
*  Number of rows affected. If 0, then operation has failed
*/
function textus_delete_annotation($noteid) {
  $rows = textus_db_delete_annotation($noteid);
  if ($rows)
  {
    return $rows;
  }
}

/**
*  Function to insert the annotation into the table
* 
*
*  return int
*  returns the number of rows affected. Should only be 1. If not, the calling function needs to throw an error.
*/
function textus_db_insert_annotation($userid, $textid, $start, $end, $private, $lang, $text)
{
    global $wpdb;

    $table_name = $wpdb->prefix . "textus_annotations"; 
   print "user is $userid";
   $rows_affected = $wpdb->insert( $table_name, 
     array( 
       'textid' => $textid,
       'start' => $start, 
       'end' => $end, 
       'userid' => $userid,
       'private' => $private,
       'language' => $lang,
       'text' => $text
        ) 
      );
    return $rows_affected;
}

/**
*  Function to get the text annotations for a given id
*
*  @param textid
*  The text id given from the API
*
*  @return array
*  Returns an array of the annotations to be jsonified later
*/
function textus_get_annotations($textid)
{
   global $currentuser;
    $annotations = array();
   if (!$textid) {
     return wp_send_json("No text id was given");
   }

   $notes = textus_db_select_annotation($textid);
   if (!$notes)
   {
      //actually do we want to return an empty JSON message?
      $annotations = array('error' => 'No annotations could be found for this text' );
   } else {
     foreach ($notes as $note)
     {
          $note_user = get_user_by('id', $note->userid);
         // put the notes into the correct structure
         $annotations[] = array(
            "id" => intval($note->id),
            "start" => intval($note->start), 
            "end" => intval($note->end), 
            "type" => "textus:comment",
            "user" => $note_user->user_nicename,
            //"time" => $note->time, 
            //"private" => $note->private, 
            "payload" => array(
               //"language" => $note->language, 
               "text" => $note->text)
            );
     }
   }
   return $annotations;
}

/**
*   Function to get the annotations from the store
*/
function textus_db_select_annotation($textid)
{
  global $wpdb;
  if (!$textid) {
     return wp_send_json(array("status"=>500, "error"=>"No text id was given"));
  }
  $notes = $wpdb->get_results( 
   "SELECT id, start, end, time, userid, private, language, text
    FROM " . $wpdb->prefix . "textus_annotations
    WHERE textid='$textid'"
  );

   if ($notes) 
   {
      return $notes;
   }
}

/**
*   Function to get user id from the given "nice_name"
*/
function textus_db_get_id($name)
{
  global $wpdb;
  if (!$name) {
     return wp_send_json(array("status"=>500, "error"=>"No username was given"));
  }
  $notes = $wpdb->get_var( 
   "SELECT ID
    FROM " . $wpdb->prefix . "users
    WHERE user_nicename='$name'"
  );

   if ($notes) 
   {
      return $notes;
   }
}

/**
*  Update the store
*/
function textus_db_update_annotation ($userid, $textid, $start, $end, $private, $lang, $text,$id) {
  global $wpdb;
  $updates = $wpdb->update( $wpdb->prefix."textus_annotations", 
     array( 
       'start' => $start, 
       'end' => $end, 
       'userid' => $userid,
       'private' => $private,
       'language' => $lang,
       'text' => $text
      ),
      array('id' => $id), 
      $format = null, 
      $where_format = null 
  );
  if ($updates) {
     return $updates;
  }
}

function textus_db_delete_annotation ($noteid) {
  global $wpdb;
  $delete = $wpdb->delete( $wpdb->prefix."textus_annotations", 
      array('id' => $noteid), 
      $format = null, 
      $where_format = null 
  );
  if ($delete) {
     return $delete;
  }
}

/* Rewrite rules */
function textus_activation() {
  // Add the rewrite rule on activation
  global $wp_rewrite;
  add_filter('rewrite_rules_array', 'textus_rewrites');
  $wp_rewrite->flush_rules();
}

function textus_deactivation() {
  // Remove the rewrite rule on deactivation
  global $wp_rewrite;
  $wp_rewrite->flush_rules();
}

function textus_rewrites($wp_rules) {
  /*$base = get_option('textus_base', 'api');
  if (empty($base)) {
    return $wp_rules;
  }*/
  // hardcoded but we need to make this configurable
  $base = 'textus.php';
  $textus_rules = array(
    "$base\$" => 'index.php?text=$matches[1]',
    "$base/(.+)\$" => 'index.php?text=$matches[1]&type=$matches[2]'
  );
  return array_merge($textus_rules, $wp_rules);
}



?>
