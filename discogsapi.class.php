<?php
/**
 * DiscogsAPI.class.php
 *
 * @package DiscogsAPI
 * @version 0.1
 * @link http://machiasman.co.cc/blog/discogsapi-class/
 */
namespace DiscogsAPI;

class DiscogsAPI {
	public $useragent;
	public $api_url;
	public $errors = array();
	public $anonymous_artists = array('Various','Unknown Artist');

	function __construct(){
		global $useragent;
		if($useragent){
			$this->useragent = $useragent;
		}
	}


	function log_error($e){
		$this->errors[] = $e;
		return $this->errors;
	}
	
	private function jsonerror($e){
	
		switch ($e) {
			case JSON_ERROR_NONE:
				return ' - No errors';
			break;
			case JSON_ERROR_DEPTH:
				return ' - Maximum stack depth exceeded';
			break;
			case JSON_ERROR_STATE_MISMATCH:
				return ' - Underflow or the modes mismatch';
			break;
			case JSON_ERROR_CTRL_CHAR:
				return ' - Unexpected control character found';
			break;
			case JSON_ERROR_SYNTAX:
				return ' - Syntax error, malformed JSON';
			break;
			case JSON_ERROR_UTF8:
				return ' - Malformed UTF-8 characters, possibly incorrectly encoded';
			break;
			default:
				return ' - Unknown error';
			break;
		}
	
	}
	
	public function discogsjson($url){
		if( ! isset($this->useragent) || $this->useragent==''){
			$this->log_error("No useragent set");
			return false;
		}

		$curl = curl_init();
		$this->api_url = $url;
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_ENCODING, "gzip");
		curl_setopt($curl, CURLOPT_USERAGENT, $this->useragent);
		$json = curl_exec($curl);
		curl_close($curl);
	
		$data = json_decode($json,true);
		if(json_last_error() == JSON_ERROR_NONE){
			if($data['error']) {
				$this->log_error("Discogs API: ". $data['error']);
				$this->log_error("API URL: ". $url);
				$this->log_error("Discogs Response: ". $json);
				return false;
			} else {
				return $data;
			}
		} else {
			$this->log_error("Discogs API JSON Error: ". $this->jsonerror(json_last_error()));
			$this->log_error("API URL: ". $url);
			$this->log_error("Discogs Response: ". $json);
			return false;		
		}
		
		return $data;
	}

	public function getRelease($release_id){
		$url = "http://api.discogs.com/release/". $release_id;
		$data = $this->discogsjson($url);
		return $data;
	}

	public function getLabel($label,$releases = false){
		$url = "http://api.discogs.com/label/". urlencode($label);
		if($releases){
			$url .= "?releases=1&f=json";
		}
		$data = $this->discogsjson($url);
		return $data;
		
	}

	public function getArtist($artist,$releases = false){
		$url = "http://api.discogs.com/artist/". urlencode($artist);
		if($releases){
			$url .= "?releases=1&f=json";
		}
		$data = $this->discogsjson($url);
		return $data;
	}

	public function getSearch($search,$type='all',$page=1){		
		$url = "http://api.discogs.com/search?q=". urlencode($search)."&f=json";
		if($type){
			$url .= "&type=" . $type;
		}
		if($page){
			$url .= "&page=" . $page;
		}
		$data = $this->discogsjson($url);
		return $data;
	}


	public function getMasterRelease($master_id){
		$url = "http://api.discogs.com/master/". $master_id;
		$data = $this->discogsjson($url);
		return $data;
	}
	
	public function anonartist($artist){
		if( in_array($artist,$this->anonymous_artists) ){
			return true;
		} else {
			return false;
		}
	}



} // end DiscogsAPI Class

class Release extends DiscogsAPI {
	public $release;
	public $release_id;
	public $data;
	public $labels;
	public $artists;
	public $unique_artists;
	/* Field-level strings */
	public $country, $title, $notes, $released, $released_formatted, $year, $master_id;
	
	
	public function __construct($release_id){
		parent::__construct();
	
		if($release_id){
			$this->release_id = $release_id;
			$this->load();
		}
	}
	
	
/**
 * Main function to load Release data from API.  $Release_ID must be set.
*/
	public function load(){
		if(! $this->release_id){
			$this->log_error("No release id set.");
			return false;
		}
		
		if(! $this->data){
			$this->data = $this->getRelease($this->release_id);
		}
		
		/*Create root release array */
		$this->release = &$this->data['resp']['release'];
		
		/* Set field-level strings */		
		$this->country 		= (string) $this->release['country'];
		$this->title 		= (string) $this->release['title'];
		$this->notes 		= (string) $this->release['notes'];
		$this->released		= (string) $this->release['released'];
		$this->released_formatted 		= (string) $this->release['released_formatted'];
		$this->year			= (string) $this->release['year'];
		$this->master_id	= (string) $this->release['master_id'];
		
		return $this->data;
	}
	
	private function checkdata(){
		if(! $this->release_id){
			$this->log_error("No release id set.");
			return false;
		}
		if(! $this->data){
			$this->load($this->release_id);
		}
		$release =  &$this->data['resp']['release'];
		return $release;
	}
	
	public function find_artists($depth=1){
		if(! $this->release_id){
			$this->log_error("No release id set.");
			return false;
		}
		if(! $this->data){
			$this->load($this->release_id);
		}
		$release =  &$this->release;

		foreach($release['artists'] as $artist){
			$artist_arr[] = utf8_decode((string) $artist['name']);
		}
		if($depth==2){
			foreach($release['tracklist'] as $track){
				foreach($track['artists'] as $artist){
					$artist_arr[] = utf8_decode((string) $artist['name']);
				}
			}
		}
		$this->unique_artists = array_unique($artist_arr);
		return array_unique($artist_arr);
	}
	
	public function artists_str(){
		if(! $this->release_id){
			$this->log_error("No release id set.");
			return false;
		}
		if(! $this->data){
			$this->load($this->release_id);
		}
		
		$release =  &$this->release;

		foreach($release['artists'] as $artist){
			$artist_arr[] = utf8_decode((string) $artist['name']);
			$artist_arr[] = utf8_decode((string) $artist['join']);
		}

		return trim(implode(' ',$artist_arr));
	
	} //end artist_str

	public function load_artists($depth=1){
		if(! $this->release_id){
			$this->log_error("No release id set.");
			return false;
		}
		if(! $this->data){
			$this->load($this->release_id);
		}
		
			if(! count($this->unique_artists) == 0 ){
				$this->find_artists($all);
			}
	
			foreach($this->find_artists($depth) as $a){
				if(! $this->anonartist($a) ) {
					$artist = new Artist;
					$artist->id = $a;
					$artist->useragent = $this->useragent;
					$artist->load(false);
					$at[] 	= $artist;
				}
			}
			$this->artists = $at;

		return $this->artists;
	}



	public function labels_str($sep = ', '){
		if(! $this->release_id){
			$this->log_error("No release id set.");
			return false;
		}
		if(! $this->data){
			$this->load($this->release_id);
		}
		
		$release =  &$this->release;

		foreach($release['labels'] as $lbl){
			$labels[] 	= $lbl['name'];
		}

		return trim(implode($sep,$labels));
	
	} //end labels_str


	public function catnos_str($sep = ', '){
		if(! $this->release_id){
			$this->log_error("No release id set.");
			return false;
		}
		if(! $this->data){
			$this->load($this->release_id);
		}
		
		$release =  &$this->release;

		foreach($release['labels'] as $lbl){
			$catnos[] 	= $lbl['catno'];
		}

		return trim(implode($sep,$catnos));
	
	} //end catnos

	
	public function load_labels(){
		if(! $this->release_id){
			$this->log_error("No release id set.");
			return false;
		}
		if(! $this->data){
			$this->load($this->release_id);
		}
		if(! $this->labels ){
			$release =  &$this->release;
	
			foreach($release['labels'] as $lbl){
				$label = new Label;
				$label->useragent = $this->useragent;
				$label->id = $lbl['name'];
				$label->load(false);
				$l[] 	= $label;
			}
			$this->labels = $l;
		}
		return $this->labels;
	}
	
	public function formats_str($sep=', '){
		if(! $this->release_id){
			$this->log_error("No release id set.");
			return false;
		}
		if(! $this->data){
			$this->load($this->release_id);
		}
		
		$release =  &$this->release;
		
		foreach($release['formats'] as $format) {
			// get properties
			$format_arr[] =  utf8_decode((string) $format['name']);
			$format_arr[] = 'x' . (string) $format['qty'];
			$format_arr[] = utf8_decode((string) $format['descriptions'][0]);
		}
		return trim(implode($sep,$format_arr));
	} //end formats

	public function genres_str($sep=', '){
		if(! $this->release_id){
			$this->log_error("No release id set.");
			return false;
		}
		if(! $this->data){
			$this->load($this->release_id);
		}
		
		$release =  &$this->release;
		
		foreach($release['genres'] as $g) {
			// get properties
			$genre_arr[] =  utf8_decode((string) $g);
		}
		return trim(implode($sep,$genre_arr));
	} //end genres


	public function styles_str($sep=', '){
		if(! $this->release_id){
			$this->log_error("No release id set.");
			return false;
		}
		if(! $this->data){
			$this->load($this->release_id);
		}
		
		$release =  &$this->release;
		
		foreach($release['styles'] as $s) {
			// get properties
			$styles_arr[] =  utf8_decode((string) $s);
		}
		return trim(implode($sep,$styles_arr));
	} //end styles

	public function tracks(){
		if(! $this->release_id){
			$this->log_error("No release id set.");
			return false;
		}
		if(! $this->data){
			$this->load($this->release_id);
		}
		
		$release =  &$this->release;
		
		foreach($release['tracklist'] as $t) {
			$trk[] =  $t;
		}
		
		return $trk;
	}

} //end release class


class MasterRelease extends DiscogsAPI {
	public $master_id;
	public $data;
	public $masterrelease;
	public $releases;
	public $title, $videos, $versions, $main_release, $notes, $artists, $year, $images, $tracklist;
	
	public function __construct($master_id){
		parent::__construct();

		$this->master_id = $master_id;
		$this->load();
	}
	
	public function load(){
		if(! $this->master_id){
			$this->log_error("No Master id set.");
			return false;
		}
		
		if(! $this->data){
			$this->data = $this->getMasterRelease($this->master_id);
		} 

		$this->masterrelease = &$this->data['resp']['master'];
		
		$this->videos					= &$this->masterrelease['videos'];
		$this->versions					= &$this->masterrelease['versions'];
		$this->main_release				= &$this->masterrelease['main_release'];
		$this->notes					= &$this->masterrelease['notes'];
		$this->artists					= &$this->masterrelease['videos'];
		$this->year						= &$this->masterrelease['videos'];
		$this->images					= &$this->masterrelease['images'];
		$this->tracklist				= &$this->masterrelease['tracklist'];
		
		foreach($this->versions as $v){
			if($v['id'] == $this->main_release){
				$this->title = $v['title'];
				break;
			}
		}
		
		return $this->data;
	}
	
	public function artists_str(){
		if(! $this->master_id){
			$this->log_error("No Master id set.");
			return false;
		}
		if(! $this->data){
			$this->load($this->master_id);
		}
		
		$master = &$this->data['resp']['master'];

		foreach($master['artists'] as $artist){
			$artist_arr[] = utf8_decode((string) $artist['name']);
			$artist_arr[] = utf8_decode((string) $artist['join']);
		}

		return trim(implode(' ',$artist_arr));
	
	} //end artist_str

	public function labels_str($sep = ', '){
		if(! $this->master_id){
			$this->log_error("No Master id set.");
			return false;
		}
		if(! $this->data){
			$this->load($this->master_id);
		}
		
		$master = &$this->data['resp']['master'];

		foreach($master['versions'] as $v){
			$labels[] 	= $v['label'];
		}

		return trim(implode($sep,$labels));
	
	} //end labels_str
	
	public function formats_str($sep='; '){
		if(! $this->master_id){
			$this->log_error("No Master id set.");
			return false;
		}
		if(! $this->data){
			$this->load($this->master_id);
		}
		
		$master = &$this->data['resp']['master'];
		
		foreach($master['versions'] as $v) {
			// get properties
			$format_arr[] =  utf8_decode((string) $v['format']);
		}
		return trim(implode($sep,$format_arr));
	} //end formats

	public function genres_str($sep=', '){
		if(! $this->master_id){
			$this->log_error("No Master id set.");
			return false;
		}
		if(! $this->data){
			$this->load($this->master_id);
		}
		
		$master = &$this->data['resp']['master'];
		
		foreach($master['genres'] as $g) {
			// get properties
			$genre_arr[] =  utf8_decode((string) $g);
		}
		return trim(implode($sep,$genre_arr));
	} //end genres


	public function styles_str($sep=', '){
		if(! $this->master_id){
			$this->log_error("No Master id set.");
			return false;
		}
		if(! $this->data){
			$this->load($this->master_id);
		}
		
		$master = &$this->data['resp']['master'];
		
		foreach($master['styles'] as $s) {
			// get properties
			$styles_arr[] =  utf8_decode((string) $s);
		}
		return trim(implode($sep,$styles_arr));
	} //end styles


	public function load_releases(){
		if(! $this->master_id){
			$this->log_error("No Master id set.");
			return false;
		}
		if(! $this->data){
			$this->load($this->master_id);
		}
		
		if(! $this->releases ){		
			$master = &$this->data['resp']['master'];
			$r = array();
			foreach($master['versions'] as $v) {
				$release = new Release;
				$release->useragent = $this->useragent;
				$release->release_id = $v['id'];
				$release->load();
				$r[] = $release;
			}
			$this->releases = $r;
		}
		return $this->releases;
	}


} //end master class

class Label extends DiscogsAPI {
	public $id;
	public $data;
	public $label;
	public $name, $contactinfo, $parentlabel, $images, $sublabels;

	public function __construct($label){
		parent::__construct();

		$this->id = $label;
		$this->load();
	}

	public function load($releases=false){
		if(! $this->id){
			$this->log_error("No Label name/id set");
			return false;
		}
		
		if(! $this->data){
			$this->data = $this->getLabel($this->id,$releases);
			$this->label = &$this->data['resp']['label'];
		} else {
			$this->label = &$this->data['resp']['label'];
		}

		$this->name				= &$this->label['name'];
		$this->contactinfo		= &$this->label['contactinfo'];
		$this->parentlabel		= &$this->label['parentlabel'];
		$this->sublabels		= &$this->label['sublabels'];
		$this->images			= &$this->label['images'];

		return $this->data;
	}

} //end Label class

class Artist extends DiscogsAPI {
	public $id;
	public $data;
	public $artist;
	public $name, $namevariations, $urls, $images, $realname, $aliases;

	public function __construct($artist){
		parent::__construct();

		$this->id = $artist;
		$this->load();
	}

	public function load($releases=false){
		if(! $this->id){
			$this->log_error("No Label name/id set");
			return false;
		}
		
		if(! $this->data){
			$this->data = $this->getArtist($this->id,$releases);
			$this->artist = &$this->data['resp']['artist'];
		} else {
			$this->artist = &$this->data['resp']['artist'];
		}

		$this->name				= &$this->artist['name'];
		$this->namevariations	= &$this->artist['namevariations'];
		$this->urls				= &$this->artist['urls'];
		$this->images			= &$this->artist['images'];
		$this->realname			= &$this->artist['realname'];
		$this->aliases			= &$this->artist['aliases'];


		return $this->data;
	}

} //End Artist Class

class Search extends DiscogsAPI {
	public $search_str;
	public $page=1;
	public $type = 'all';
	public $next_page=1;
	public $prev_page;
	public $result_count;
	public $start;
	public $end;
	public $search_results;
	public $exact_results;
	public $max_page=1;
	
	function __construct(){
		parent::__construct();
	
	}
	
	public function search($search ='', $type = '', $page = 1){
		if($search){
			$this->search_str = $search;
		} else{
			$search = $this->search_str;
		}
		if($type){
			$this->type = $type;
		} else {
			$type = $this->type;
		}
		if($page){
			$this->page = $page;
		} else {
			$page = $this->page;
		}
		
		//validate type
		$type = $this->validatetype($type);
		
		$data = $this->getSearch($search,$type,$page);
		
		$this->result_count 			= $data['resp']['search']['searchresults']['numResults'];
		$this->start 					= $data['resp']['search']['searchresults']['start'];
		$this->end 						= $data['resp']['search']['searchresults']['end'];
		$this->search_results 			= $data['resp']['search']['searchresults'];
		$this->exact_results 			= $data['resp']['search']['exactresults'];
		
		$this->setpagebounds();
		
		return $data;
	}
	
	private function validatetype($type){
		$valid_type = array('all','releases','artists','labels');
		if( in_array($type,$valid_type) ){
			return $type;
		} else {
			return 'all';
		}	
	}
	
	private function setpagebounds(){
		if( ($this->result_count % 20) > 0){
			$this->max_page = floor( ($this->result_count / 20) ) + 1;
		} else {
			$this->max_page = $this->result_count / 20;
		}
		
		//Wrap results
		if( ($this->page + 1) >= $this->max_page){
			$this->next_page = 1;
		} else {
			$this->next_page = $this->page + 1;
		}
		
		if( $this->page > 1){
			$this->prev_page = $this->page -1;
		} else {
			$this->prev_page = $this->max_page;
		}
	}
	
	public function next_page(){
		return $this->search('','',$this->next_page);
	}

	public function prev_page(){
		return $this->search('','',$this->prev_page);
	}
	
	private function reset_search(){
		$this->page = 1;
		$this->next_page = 2;
		$this->result_count = '';
		$this->start = null;
		$this->end = null;
		$this->search_results = null;
	}
}

?>