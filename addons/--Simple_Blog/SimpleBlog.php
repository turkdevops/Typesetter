<?php
defined('is_running') or die('Not an entry point...');

gpPlugin::incl('SimpleBlogCommon.php','require_once');

/**
 * Class for displaying the Special_Blog page and performing it's actions
 *
 */

class SimpleBlog extends SimpleBlogCommon{

	var $showing_category = false;

	function __construct(){
		global $page, $langmessage, $addonFolderName;

		$this->Init();

		//get the post id
		if( isset($_REQUEST['id']) && ctype_digit($_REQUEST['id']) ){
			$this->post_id = $_REQUEST['id'];

		}elseif( strpos($page->requested,'/') !== false ){
			$parts = explode('/',$page->requested);
			$ints = strspn($parts[1],'0123456789');
			if( $ints > 0 ){
				$this->post_id = substr($parts[1],0,$ints);
			}
		}


		$cmd	= common::GetCommand();
		$show	= true;

		if( common::LoggedIn() ){

			switch($cmd){

				/* inline editing */
				case 'inlineedit':
					$this->InlineEdit();
				die();
				case 'save_inline':
				case 'save':
					$this->SaveInline();
				break;


				//delete
				case 'deleteentry':
				case 'delete':
					if( $this->Delete() ){
						$this->GenStaticContent();
					}
				break;

				//editing
				case 'save_edit':
					if( $this->SaveEdit() ){
						$this->GenStaticContent();
						break;
					}
				case 'edit':
				case 'edit_post';
					$this->EditPost();
					$show = false;
				break;

				//creating
				case 'save_new';
					if( $this->SaveNew() ){
						$this->GenStaticContent();
						break;
					}
				case 'new_form':
					$this->NewForm();
					$show = false;
				break;

			}

			$page->admin_links[] = array('Special_Blog','Blog Home');

			$page->admin_links[] = array('Special_Blog','New Blog Post','cmd=new_form');

			$page->admin_links[] = array('Admin_Blog','Configuration');

			$page->admin_links[] = array('Admin_Theme_Content',$langmessage['editable_text'],'cmd=addontext&addon='.urlencode($addonFolderName),' name="gpabox" ');

			$label = 'Number of Posts: '. SimpleBlogCommon::$data['post_count'];
			$page->admin_links[$label] = '';
		}


		if( $show ){

			//post requests
			if( empty($cmd) ){
				if( $this->post_id > 0 ){
					$cmd = 'post';
				}
			}
			switch($cmd){
				case 'opencomments':
				case 'closecomments':
				case 'delete_comment':
				case 'Add Comment':
				case 'save_edit':
				case 'post':
					$this->ShowPost($cmd);
				break;
				case 'page':
				default:
					$this->ShowPage();
				break;
			}

			if( common::LoggedIn() && !file_exists(self::$index_file) ){
				echo '<p>Congratulations on successfully installing Simple Blog for gpEasy.</p> ';
				echo '<p>You\'ll probably want to get started by '.common::Link('Special_Blog','creating a blog post','cmd=new_form').'.</p>';
			}

		}

	}



	/**
	 * Output the html for a single blog post
	 * Handle comment actions
	 */
	function ShowPost($cmd){
		global $langmessage, $page;

		gpPlugin::incl('SimpleBlogPage.php','require_once');

		$blog_page = new SimpleBlogPage($this->post_id);

		$blog_page->ShowPost();

		return;


		switch($cmd){


			//close comments
			case 'closecomments':
				$this->CloseComments($this->post_id);
			break;
			case 'opencomments':
				$this->OpenComments($this->post_id);
			break;


			//commments
			case 'delete_comment':
				$this->DeleteComment($this->post_id);
			break;
		}

	}



	/**
	 * Close the comments for a blog post
	 *
	 */
	function CloseComments($post_index){
		global $langmessage;

		SimpleBlogCommon::AStrValue('comments_closed',$post_index,1);
		if( !SimpleBlogCommon::SaveIndex() ){
			message($langmessage['OOPS']);
		}else{
			message($langmessage['SAVED']);
		}
	}

	/**
	 * Allow commenting for a blog post
	 *
	 */
	function OpenComments($post_index){
		global $langmessage;

		SimpleBlogCommon::AStrRm('comments_closed',$post_index);
		if( !SimpleBlogCommon::SaveIndex() ){
			message($langmessage['OOPS']);
		}else{
			message($langmessage['SAVED']);
		}
	}





	/**
	 * Display a blog page with multiple blog posts
	 *
	 */
	function ShowPage(){

		$per_page = SimpleBlogCommon::$data['per_page'];
		$page = 0;
		if( isset($_GET['page']) && is_numeric($_GET['page']) ){
			$page = (int)$_GET['page'];
		}
		$start = $page * $per_page;

		$include_drafts = common::LoggedIn();
		$show_posts = $this->WhichPosts($start,$per_page,$include_drafts);

		$this->ShowPosts($show_posts);

		//pagination links
		echo '<p class="blog_nav_links">';

		if( $page > 0 ){

			$html = common::Link('Special_Blog','%s');
			echo gpOutput::GetAddonText('Blog Home',$html);
			echo '&nbsp;';

			$html = common::Link('Special_Blog','%s','page='.($page-1),'class="blog_newer"');
			echo gpOutput::GetAddonText('Newer Entries',$html);
			echo '&nbsp;';

		}

		if( ( ($page+1) * $per_page) < SimpleBlogCommon::$data['post_count'] ){
			$html = common::Link('Special_Blog','%s','page='.($page+1),'class="blog_older"');
			echo gpOutput::GetAddonText('Older Entries',$html);
		}


		if( common::LoggedIn() ){
			echo '&nbsp;';
			echo common::Link('Special_Blog','New Post','cmd=new_form');
		}

		echo '</p>';

	}


	/**
	 * Output the blog posts in the array $post_list
	 *
	 */
	function ShowPosts($post_list){

		$posts = array();
		foreach($post_list as $post_index){
			$post	= SimpleBlogCommon::GetPostContent($post_index);
			$this->ShowPostContent( $post, $post_index, SimpleBlogCommon::$data['post_abbrev'], 'post_list_item' );
		}

	}

	/**
	 * Display the html for a single blog post
	 *
	 */
	function ShowPostContent( &$post, &$post_index, $limit = 0, $class = '' ){
		global $langmessage;

		if( !common::LoggedIn() && SimpleBlogCommon::AStrValue('drafts',$post_index) ){
			return false; //How to make 404 page?
		}

		//If user enter random Blog url, he didn't see any 404, but nothng.
		$id = '';
		$class = $class == '' ? '' : ' '.$class;
		if( common::LoggedIn() ){

			$query = 'du'; //dummy parameter
			SimpleBlogCommon::UrlQuery( $post_index, $url, $query );
			$edit_link = gpOutput::EditAreaLink($edit_index,$url,$langmessage['edit'].' (TWYSIWYG)',$query,'name="inline_edit_generic" rel="text_inline_edit"');

			echo '<span style="display:none;" id="ExtraEditLnks'.$edit_index.'">';
			echo $edit_link;

			echo SimpleBlogCommon::PostLink($post_index,$langmessage['edit'].' (All)','cmd=edit_post',' style="display:none"');
			echo common::Link('Special_Blog',$langmessage['delete'],'cmd=deleteentry&del_id='.$post_index,array('class'=>'delete gpconfirm','data-cmd'=>'cnreq','title'=>$langmessage['delete_confirm']));

			if( SimpleBlogCommon::$data['allow_comments'] ){

				$comments_closed = SimpleBlogCommon::AStrValue('comments_closed',$post_index);
				if( $comments_closed ){
					$label = gpOutput::SelectText('Open Comments');
					echo SimpleBlogCommon::PostLink($post_index,$label,'cmd=opencomments','name="creq" style="display:none"');
				}else{
					$label = gpOutput::SelectText('Close Comments');
					echo SimpleBlogCommon::PostLink($post_index,$label,'cmd=closecomments','name="creq" style="display:none"');
				}
			}

			echo common::Link('Special_Blog','New Blog Post','cmd=new_form',' style="display:none"');
			echo common::Link('Admin_Blog',$langmessage['configuration'],'',' style="display:none"');
			echo '</span>';
			$class .= ' editable_area';
			$id = 'id="ExtraEditArea'.$edit_index.'"';
		}

		$isDraft = '';
		if( SimpleBlogCommon::AStrValue('drafts',$post_index) ){
			$isDraft = '<span style="opacity:0.3;">';
			$isDraft .= gpOutput::SelectText('Draft');
			$isDraft .= '</span> ';
		}
		echo '<div class="blog_post'.$class.'" '.$id.'>';

		$header = '<h2 id="blog_post_'.$post_index.'">';
		$header .= $isDraft;
		$label = SimpleBlogCommon::Underscores( $post['title'] );
		$header .= SimpleBlogCommon::PostLink($post_index,$label);
		$header .= '</h2>';

		SimpleBlogCommon::BlogHead($header,$post_index,$post);

		echo '<div class="twysiwygr">';
		echo $this->AbbrevContent( $post['content'], $post_index, $limit);
		echo '</div>';

		echo '</div>';

		echo '<br/>';

		echo '<div class="clear"></div>';

	}

	/**
	 * Abbreviate $content if a $limit greater than zero is given
	 *
	 */
	function AbbrevContent( $content, $post_index, $limit = 0 ){

		if( !is_numeric($limit) || $limit == 0 ){
			return $content;
		}

		$content = strip_tags($content);

		if( SimpleBlogCommon::strlen($content) < $limit ){
			return $content;
		}

		$pos = SimpleBlogCommon::strpos($content,' ',$limit-5);

		if( ($pos > 0) && ($limit+20 > $pos) ){
			$limit = $pos;
		}
		$content = SimpleBlogCommon::substr($content,0,$limit).' ... ';
		$label = gpOutput::SelectText('Read More');
		return $content . SimpleBlogCommon::PostLink($post_index,$label);
	}



	/* comments */




	/**
	 * Remove a comment entry from the comment data
	 *
	 */
	function DeleteComment($post_index){
		global $langmessage;

		$data = $this->GetCommentData($post_index);

		$comment = $_POST['comment_index'];
		if( !isset($data[$comment]) ){
			message($langmessage['OOPS']);
			return;
		}

		unset($data[$comment]);

		if( $this->SaveCommentData($post_index,$data) ){
			message($langmessage['SAVED']);
			return true;
		}else{
			message($langmessage['OOPS']);
			return false;
		}

	}


}


