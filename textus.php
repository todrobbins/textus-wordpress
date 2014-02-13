<?php
/*
Plugin Name: Open Literature
Plugin URI: http://github.com/OpenHumanities
Description: A plugin to insert an Open Literature instance into Wordpress. 
Author: OKFN
Version: 0.1
Author URI: http://www.openliterature.net
*/
include __DIR__ .'/controller/get_text_controller.php';

// set up the Textus slug API
add_action('init', 'register_textus');

//Set up the Textus API
add_action('init', 'textus_get_control');
add_shortcode('textus', 'textus_shortcode');

// function to create annotation table
register_activation_hook( __FILE__, 'textus_install' );

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
    
    $rawtext = textus_get_text($id, 'text');   
    $rawjson = textus_get_text($id, 'json');
    // return the text with the call the the Javascript location
    return '<div id="raw">
'.$rawtext.'
</div>
<script src="/vendor/textus-viewer.js"></script>
<script type="text/javascript">
var textusTypography = "'.$rawjson.'";
var textUrl = "'.$rawtext.'";
var apiUrl = "";
var currentUser = { id : '.get_current_user_id().'};
viewer = new Viewer(textUrl, textusTypography, apiUrl, currentUser);
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
            //needs testing against the textus code
            $server = true;
            break;
          default:
            wp_send_json(array("error" =>"Term not supported"));
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
               $parse = parse_parameters();
               if ( $_GET['type'] == 'annotation' ) {
                  if (intval($_GET['text'])) {
                     return_response(array("status"=>200, "notes"=>textus_get_annotations($_GET['text'])));
                  } else {
                     return_response(array("status" => 403, "error"=>"You need to specify a text"));
                  }
                }
                break;
            case 'POST':
              //@todo get the vars which the textus viewer sets
              $textid = parse_parameters($_POST);
              // returns the new noteid
              $noteid = textus_insert_annotation($_POST['time'], 
                $_POST['start'], $_POST['end'], 
                $_POST['userid'], $_POST['private'], 
                $_POST['lang'], $_POST['text']
              );
              break;
            case 'PUT':
              //@todo get the vars which the textus viewer sets
              $textid = parse_parameters();
              // returns the new noteid
              $noteid = textus_insert_annotation($_POST['id'], $_POST['time'], 
                $_POST['start'], $_POST['end'], 
                $_POST['userid'], $_POST['private'], 
                $_POST['lang'], $_POST['text']
              );
              break;
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
function parse_parameters()
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
                /*$data = file_get_contents("php://input");
                $body_params = json_decode($data);*/
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
function textus_insert_annotation($start, $end, $userid, $private, $lang, $text) {
  $rows = textus_db_insert_annotation($start, $end, $userid, $private, $lang, $text);
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
function textus_updates_annotation($id, $time, $start, $end, $userid, $private, $lang, $text) {
  $rows = textus_db_insert_annotation($id, $time, $start, $end, $userid, $private, $lang, $text);
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
function textus_db_insert_annotation($start, $end, $userid, $private, $lang, $text)
{
    global $wpdb;
   $rows_affected = $wpdb->insert( $table_name, array( 'time' => current_time('mysql'), 'name' => $welcome_name, 'text' => $welcome_text ) );
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
         // put the notes into the correct structure
         $annotations[] = array(
            "start" => $note->start, 
            "end" => $note->end, 
            "time" => $note->time, 
            "private" => $note->private, 
            "payload" => array(
               "language" => $note->language, 
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
   "SELECT start, end, time, userid, private, language, text
    FROM " . $wpdb->prefix . "textus_annotations
    WHERE textid='$textid'"
  );

   if ($notes) 
   {
      return $notes;
   }
}

?>
