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

namespace pocketmine\inventory;

use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\Player;

class CraftingGrid extends BaseInventory{
	/** @var Player */
	protected $holder;

	public function __construct(Player $holder){
		$this->holder = $holder;
		parent::__construct();
	}

	public function getGridWidth() : int{
		return 2;
	}

	public function getDefaultSize() : int{
		return $this->getGridWidth() ** 2;
	}

	public function setSize(int $size){
		throw new \BadMethodCallException("Cannot change the size of a crafting grid");
	}

	public function getName() : string{
		return "Crafting";
	}

	public function sendSlot(int $index, $target) : void{
		//we can't send a slot of a client-sided inventory window
	}

	public function sendContents($target) : void{
		//no way to do this
	}

	/**
	 * @return Player
	 */
	public function getHolder(){
		return $this->holder;
	}

	/**
	 * Returns a 2D array of items in this crafting grid, trimmed of empty rows and columns. Used for recipe matching.
	 *
	 * @return Item[][]
	 */
	public function getTrimmedItemMap() : array{
		$minX = PHP_INT_MAX;
		$maxX = 0;

		$minY = PHP_INT_MAX;
		$maxY = 0;

		$empty = true;

		$gridSize = $this->getGridWidth();
		for($y = 0; $y < $gridSize; ++$y){
			for($x = 0; $x < $gridSize; ++$x){
				if(!$this->isSlotEmpty($y * $gridSize + $x)){
					$minX = min($minX, $x);
					$maxX = max($maxX, $x);

					$minY = min($minY, $y);
					$maxY = max($maxY, $y);

					$empty = false;
				}
			}
		}

		if($empty){
			return [];
		}

		$air = ItemFactory::get(Item::AIR, 0, 0);
		/** @var Item[][] $reindexed */
		$reindexed = array_fill(0, $maxY - $minY + 1, array_fill(0, $maxX - $minX + 1, $air));
		foreach($reindexed as $y => $row){
			foreach($row as $x => $item){
				$reindexed[$y][$x] = $this->getItem(($y + $minY) * $gridSize + ($x + $minX));
			}
		}

		return $reindexed;
	}
}
