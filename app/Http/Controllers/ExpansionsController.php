<?php

namespace App\Http\Controllers;

use App\Helpers\BggHelper;
use App\Models\Boardgame;
use App\Models\BoardgameExpansion;
use App\Models\Category;
use App\Models\Designer;
use App\Models\Expansion;
use App\Models\ExpansionCategory;
use App\Models\ExpansionDesigner;
use App\Models\ExpansionPublisher;
use App\Models\Publisher;
use Auth;
use Illuminate\Http\Request;
use Input;
use Validator;

class ExpansionsController extends Controller
{

	public function getExpansions()
	{
		$search = '';
		$search = \Request::get('name');

		$expansions = Expansion::where('name','like','%'.$search.'%')
			->orderBy('name', 'asc')
			->get();

		return view('expansions.index', array(
			'expansions' => $expansions,
			'search' => $search
		));
	}

	public function getNewExpansion()
	{
		$boardgames = Boardgame::orderBy('name', 'asc')->get();

		return view('expansions.add', array(
			'boardgames' => $boardgames
		));
	}

	public function postNewExpansion(Request $request)
	{
		$rules = array(
			'bgg_link' => 'required|max:255'
		);

		$validator = Validator::make(Input::all(), $rules);
		
		if ($validator->fails())
		{
			$this->throwValidationException(
				$request, $validator
			);
		}

		$expansion = new Expansion;
		$expansion->bgg_link = trim(Input::get('bgg_link'));

		$bgg_id = 0;

		if (strpos($expansion->bgg_link, 'boardgamegeek.com/boardgameexpansion') > 0) {
			$link = str_replace("https://boardgamegeek.com/boardgameexpansion","",$expansion->bgg_link);

			$link = substr($link, strpos($link, '/') + 1);

			$bgg_id = (int)$link;
		} elseif (strpos($expansion->bgg_link, 'boardgamegeek.com/boardgame') > 0) {
			$link = str_replace("https://boardgamegeek.com/boardgame","",$expansion->bgg_link);

			$link = substr($link, strpos($link, '/') + 1);

			$bgg_id = (int)$link;
		}

		$this->clearBggData($expansion);

		$publishers = [];
		$categories = [];
		$designers = [];

		if ($bgg_id > 0) {
			$bgg_data = BggHelper::getDataFromBGG($bgg_id);

			$expansion->bgg_id = $bgg_id;

			if (!empty($bgg_data)) {

				$this->setBggData($expansion, $bgg_data);

				if (!empty($bgg_data['boardgamedesigner'])){
					$designers = $bgg_data['boardgamedesigner'];
				}
				
				if (!empty($bgg_data['boardgamepublisher'])){
					$publishers = $bgg_data['boardgamepublisher'];
				}

				if (!empty($bgg_data['boardgamecategory'])){
					$categories = $bgg_data['boardgamecategory'];
				}

				$this->validate($request, [
			        'bgg_link' => 'unique:expansions,bgg_link',
			    ]);

                $expansion->save();

                foreach (Input::get('boardgames') as $boardgame_id) {
                    $mapping = new BoardgameExpansion;

                    $mapping->boardgame_id = $boardgame_id;
                    $mapping->expansion_id = $expansion->id;

                    $mapping->save();
                }

                $this->saveDesignerMappings($expansion, $designers);
                $this->saveCategoryMappings($expansion, $categories);
                $this->savePublisherMappings($expansion, $publishers);
			}
		}

		return redirect('/expansions/');
	}

	public function getEditExpansion(Expansion $expansion)
	{
		$boardgames = Boardgame::orderBy('name', 'asc')->get();

		$selected = [];

		foreach ($expansion->boardgames as $boardgame) {
			$selected[] = $boardgame->id;
		}

		return view('expansions.edit', array(
			'expansion' => $expansion,
			'boardgames' => $boardgames,
			'selected' => $selected
		));
	}

	public function postUpdateExpansion(Expansion $expansion, Request $request)
	{
		$rules = array(
			'bgg_link' => 'required|max:255'
		);

		$validator = Validator::make(Input::all(), $rules);
		
		if ($validator->fails())
		{
			$this->throwValidationException(
				$request, $validator
			);
		}

		$expansion->name = trim(Input::get('name'));
		$expansion->bgg_link = trim(Input::get('bgg_link'));
		
		$bgg_id = 0;

        if (strpos($expansion->bgg_link, 'boardgamegeek.com/boardgameexpansion') > 0) {
            $link = str_replace("https://boardgamegeek.com/boardgameexpansion","",$expansion->bgg_link);

            $link = substr($link, strpos($link, '/') + 1);

            $bgg_id = (int)$link;
        } elseif (strpos($expansion->bgg_link, 'boardgamegeek.com/boardgame') > 0) {
            $link = str_replace("https://boardgamegeek.com/boardgame","",$expansion->bgg_link);

            $link = substr($link, strpos($link, '/') + 1);

            $bgg_id = (int)$link;
        }

		$this->clearBggData($expansion);

		$publishers = [];
		$categories = [];
		$designers = [];

		$this->removeMappings($expansion);

		if ($bgg_id > 0) {
            $bgg_data = BggHelper::getDataFromBGG($bgg_id);

			$expansion->bgg_id = $bgg_id;

			if (!empty($bgg_data)) {
				
				$this->setBggData($expansion, $bgg_data);

				if (!empty($bgg_data['boardgamedesigner'])){
					$designers = $bgg_data['boardgamedesigner'];
				}
				
				if (!empty($bgg_data['boardgamepublisher'])){
					$publishers = $bgg_data['boardgamepublisher'];
				}

				if (!empty($bgg_data['boardgamecategory'])){
					$categories = $bgg_data['boardgamecategory'];
				}

				$this->validate($request, [
			        'bgg_link' => 'unique:expansions,bgg_link,' . $expansion->id,
			    ]);

                $expansion->save();

                $selected = BoardgameExpansion::where('expansion_id', '=', $expansion->id)->get();

                foreach ($selected as $mapping) {
                    $mapping->delete();
                }

                foreach (Input::get('boardgames') as $boardgame_id) {
                    $mapping = new BoardgameExpansion;

                    $mapping->boardgame_id = $boardgame_id;
                    $mapping->expansion_id = $expansion->id;

                    $mapping->save();
                }

                $this->saveDesignerMappings($expansion, $designers);
                $this->saveCategoryMappings($expansion, $categories);
                $this->savePublisherMappings($expansion, $publishers);
			}
		}

		return redirect('/expansions/');
	}

	public function getExpansion(Expansion $expansion)
	{
		return view('expansions.view', array(
			'expansion' => $expansion
		));
	}

	public function refreshBggData(){
		$loged_user = Auth::user();

		if ($loged_user->admin != 1) {
			return redirect('/expansions/');
		}

		$expansions = Expansion::get();

		foreach ($expansions as $expansion) {

			if ($expansion->bgg_id > 0) {
                $bgg_data = BggHelper::getDataFromBGG($expansion->bgg_id);

				$publishers = [];
				$categories = [];
				$designers = [];

				$this->removeMappings($expansion);

				if (!empty($bgg_data)) {
					$this->setBggData($expansion, $bgg_data);

					if (!empty($bgg_data['boardgamedesigner'])){
						$designers = $bgg_data['boardgamedesigner'];
					}
					
					if (!empty($bgg_data['boardgamepublisher'])){
						$publishers = $bgg_data['boardgamepublisher'];
					}

					if (!empty($bgg_data['boardgamecategory'])){
						$categories = $bgg_data['boardgamecategory'];
					}
				}

				$expansion->save();

				$this->saveDesignerMappings($expansion, $designers);
				$this->saveCategoryMappings($expansion, $categories);
				$this->savePublisherMappings($expansion, $publishers);
			}

		}

	    return redirect('/expansions/');
	}

	private function clearBggData(&$expansion) {
		$expansion->bgg_id = 0;
		$expansion->name = '';
		$expansion->yearpublished = NULL;
		$expansion->minplayers = NULL;
		$expansion->maxplayers = NULL;
		$expansion->minplaytime = NULL;
		$expansion->maxplaytime = NULL;
		$expansion->description = NULL;
		$expansion->thumbnail = NULL;
		$expansion->image = NULL;
		$expansion->rank = 0;
	}

	private function setBggData(&$expansion, $bgg_data) {
		$expansion->name = $bgg_data['name'];
		$expansion->yearpublished = $bgg_data['yearpublished'];
		$expansion->minplayers = $bgg_data['minplayers'];
		$expansion->maxplayers = $bgg_data['maxplayers'];
		$expansion->minplaytime = $bgg_data['minplaytime'];
		$expansion->maxplaytime = $bgg_data['maxplaytime'];
		$expansion->description = $bgg_data['description'];
		$expansion->thumbnail = $bgg_data['thumbnail'];
		$expansion->image = $bgg_data['image'];
		$expansion->rank = 0;
	}

	private function saveCategoryMappings(Expansion $expansion, $categories) {
		if (!is_array($categories)) {
			$categories = [$categories];
		}

		foreach ($categories as $category_name) {
			$category = Category::where('name', $category_name)
				->first();

			if (count($category) == 0) {
				$category = new Category;

				$category->name = $category_name;

				$category->save();
			}

			$mapping = new expansionCategory;

			$mapping->expansion_id = $expansion->id;
			$mapping->category_id = $category->id;

			$mapping->save();
		}
	}

	private function savePublisherMappings(Expansion $expansion, $publishers) {
		if (!is_array($publishers)) {
			$publishers = [$publishers];
		}

		foreach ($publishers as $publisher_name) {
			$publisher = Publisher::where('name', $publisher_name)
				->first();

			if (count($publisher) == 0) {
				$publisher = new Publisher;

				$publisher->name = $publisher_name;

				$publisher->save();
			}

			$mapping = new expansionPublisher;

			$mapping->expansion_id = $expansion->id;
			$mapping->publisher_id = $publisher->id;

			$mapping->save();
		}
	}

	private function saveDesignerMappings(Expansion $expansion, $designers) {
		if (!is_array($designers)) {
			$designers = [$designers];
		}

		foreach ($designers as $designer_name) {
			$designer = Designer::where('name', $designer_name)
				->first();

			if (count($designer) == 0) {
				$designer = new Designer;

				$designer->name = $designer_name;

				$designer->save();
			}

			$mapping = new expansionDesigner;

			$mapping->expansion_id = $expansion->id;
			$mapping->designer_id = $designer->id;

			$mapping->save();
		}
	}

	private function removeMappings(Expansion $expansion) {
		$bg_categories = ExpansionCategory::where('expansion_id', $expansion->id)
			->get();

		foreach ($bg_categories as $bg_categorie) {
			$bg_categorie->delete();
		}

		$bg_publishers = ExpansionPublisher::where('expansion_id', $expansion->id)
			->get();

		foreach ($bg_publishers as $bg_publisher) {
			$bg_publisher->delete();
		}

		$bg_desigers = ExpansionDesigner::where('expansion_id', $expansion->id)
			->get();

		foreach ($bg_desigers as $bg_desiger) {
			$bg_desiger->delete();
		}
	}

	public function getAjaxExpansions(Request $request) {
		$boardgame = Boardgame::where('id','=',$request->id)->first();

		$expansions = $boardgame->expansions;

		return \Response::json($expansions);
	}
}