<?php

namespace PerryRylance\SplitBatch;

class Clock
{
	public static function milliseconds()
	{
		return round(microtime(true) * 1000);
	}
}