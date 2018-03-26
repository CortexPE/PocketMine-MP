<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
*/

declare(strict_types=1);

namespace pocketmine\inventory\transaction;

use pocketmine\event\inventory\CraftItemEvent;
use pocketmine\inventory\CraftingGrid;
use pocketmine\inventory\CraftingRecipe;
use pocketmine\item\Item;
use pocketmine\network\mcpe\protocol\ContainerClosePacket;
use pocketmine\network\mcpe\protocol\types\ContainerIds;
use pocketmine\Player;

class CraftingTransaction extends InventoryTransaction{

	/** @var CraftingGrid */
	protected $craftingGrid;
	/** @var CraftingRecipe|null */
	protected $recipe = null;
	/** @var int|null */
	protected $iterations;

	public function __construct(Player $source, array $actions = []){
		$this->craftingGrid = $source->getCraftingGrid();

		parent::__construct($source, $actions);
	}

	/**
	 * Returns the recipe used to craft in this transaction.
	 *
	 * @return CraftingRecipe
	 */
	public function getRecipe() : CraftingRecipe{
		assert($this->recipe !== null);
		return $this->recipe;
	}

	/**
	 * Returns the number of times the recipe was crafted. This is usually 1, but might be more in the case of recipe
	 * book shift-clicks (which craft lots of items in a batch).
	 *
	 * @return int
	 */
	public function getIterations() : int{
		assert($this->iterations !== null);
		return $this->iterations;
	}

	/**
	 * @param Item[] $playerItems
	 * @param Item[] $recipeItems
	 *
	 * @return bool
	 */
	protected function matchRecipeItems(array &$playerItems, array $recipeItems) : bool{
		if(empty($recipeItems)){
			throw new \InvalidArgumentException("No recipe items given");
		}

		$matchedItems = 0;
		foreach($playerItems as $i => $playerItem){
			foreach($recipeItems as $j => $recipeItem){
				if($playerItem->equals($recipeItem, !$recipeItem->hasAnyDamageValue(), $recipeItem->hasCompoundTag())){
					$matchedItems++;

					$amount = min($playerItem->getCount(), $recipeItem->getCount());
					$playerItem->setCount($playerItem->getCount() - $amount);
					$recipeItem->setCount($recipeItem->getCount() - $amount);
					if($recipeItem->getCount() === 0){
						unset($recipeItems[$j]);
					}
					if($playerItem->getCount() === 0){
						unset($playerItems[$i]);
						break;
					}
				}
			}
		}

		return $matchedItems > 0 and count($recipeItems) === 0;
	}

	public function canExecute() : bool{
		$inputs = [];
		$outputs = [];

		$this->squashDuplicateSlotChanges();
		if(count($this->actions) < 1){
			return false;
		}

		$this->matchItems($outputs, $inputs);

		$this->recipe = $this->source->getServer()->getCraftingManager()->matchRecipe($this->craftingGrid, $outputs);
		if($this->recipe === null){
			return false;
		}

		$this->iterations = 0;
		do{
			if(++$this->iterations > 64){
				//too many loops (can't craft more than 64 repetitions in one by recipe book shift-click)
				return false;
			}

			if(!$this->matchRecipeItems($outputs, $this->recipe->getResults())){
				//failed to match all outputs
				return false;
			}
		}while(!empty($outputs));

		for($i = 0; $i < $this->iterations; ++$i){
			if(!$this->matchRecipeItems($inputs, $this->recipe->getIngredientList())){
				//not enough ingredients for detected number of iterations
				return false;
			}
		}

		if(!empty($inputs)){
			//not consumed all inputs after expected number of iterations
			return false;
		}

		return true;
	}

	protected function callExecuteEvent() : bool{
		$this->source->getServer()->getPluginManager()->callEvent($ev = new CraftItemEvent($this));
		return !$ev->isCancelled();
	}

	protected function sendInventories() : void{
		parent::sendInventories();

		/*
		 * TODO: HACK!
		 * we can't resend the contents of the crafting window, so we force the client to close it instead.
		 * So people don't whine about messy desync issues when someone cancels CraftItemEvent, or when a crafting
		 * transaction goes wrong.
		 */
		$pk = new ContainerClosePacket();
		$pk->windowId = ContainerIds::NONE;
		$this->source->dataPacket($pk);
	}

	public function execute() : bool{
		if(parent::execute()){
			foreach($this->recipe->getResults() as $item){
				switch($item->getId()){
					case Item::CRAFTING_TABLE:
						$this->source->awardAchievement("buildWorkBench");
						break;
					case Item::WOODEN_PICKAXE:
						$this->source->awardAchievement("buildPickaxe");
						break;
					case Item::FURNACE:
						$this->source->awardAchievement("buildFurnace");
						break;
					case Item::WOODEN_HOE:
						$this->source->awardAchievement("buildHoe");
						break;
					case Item::BREAD:
						$this->source->awardAchievement("makeBread");
						break;
					case Item::CAKE:
						$this->source->awardAchievement("bakeCake");
						break;
					case Item::STONE_PICKAXE:
					case Item::GOLDEN_PICKAXE:
					case Item::IRON_PICKAXE:
					case Item::DIAMOND_PICKAXE:
						$this->source->awardAchievement("buildBetterPickaxe");
						break;
					case Item::WOODEN_SWORD:
						$this->source->awardAchievement("buildSword");
						break;
					case Item::DIAMOND:
						$this->source->awardAchievement("diamond");
						break;
				}
			}

			return true;
		}

		return false;
	}
}
