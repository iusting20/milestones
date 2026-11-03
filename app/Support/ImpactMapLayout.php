<?php

namespace App\Support;

use App\Models\ImpactMapNode;
use Illuminate\Support\Collection;

/**
 * Left-to-right tree layout: each leaf gets one row; a parent sits centred on its children.
 */
class ImpactMapLayout
{
    public const ROW = 56;

    public const TOP = 70;

    /** Left edge and box width per level. */
    public const COLUMNS = [
        [20, 220],
        [300, 170],
        [530, 180],
        [770, 180],
        [1000, 160],
    ];

    /**
     * @param  Collection<int, ImpactMapNode>  $nodes
     * @return array{boxes: list<array<string, mixed>>, links: list<array<string, float>>, width: int, height: int}
     */
    public static function compute(Collection $nodes): array
    {
        $byParent = $nodes->sortBy([['position', 'asc'], ['id', 'asc']])->groupBy(fn ($n) => $n->parent_id ?? 0);
        $root = $byParent->get(0)?->first();
        $boxes = [];
        $links = [];
        $row = 0;

        if ($root !== null) {
            self::place($root, $byParent, $row, $boxes, $links);
        }

        $maxLevel = collect($boxes)->max('level') ?? 0;
        [$x, $w] = self::COLUMNS[min(ImpactMapNode::STORY, $maxLevel + 1)];

        return [
            'boxes' => $boxes,
            'links' => $links,
            'width' => max(1200, $x + $w + 40),
            'height' => self::TOP + max(1, $row) * self::ROW + 40,
        ];
    }

    private static function place(ImpactMapNode $node, Collection $byParent, int &$row, array &$boxes, array &$links): float
    {
        $children = $byParent->get($node->id, collect());
        $level = min($node->level, ImpactMapNode::STORY);
        [$x, $w] = self::COLUMNS[$level];

        if ($children->isEmpty()) {
            $y = self::TOP + $row * self::ROW + self::ROW / 2;
            $row++;
        } else {
            $ys = [];
            foreach ($children as $child) {
                $ys[] = self::place($child, $byParent, $row, $boxes, $links);
            }
            $y = (min($ys) + max($ys)) / 2;
            foreach ($ys as $childY) {
                $cx = self::COLUMNS[min($level + 1, ImpactMapNode::STORY)][0];
                $links[] = ['x1' => $x + $w, 'y1' => $y, 'x2' => $cx, 'y2' => $childY];
            }
        }

        $boxes[] = ['node' => $node, 'level' => $level, 'x' => $x, 'y' => $y, 'w' => $w];

        return $y;
    }
}
