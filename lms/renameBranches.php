<?php
header("Content-type: text/plain; charset=UTF-8");
require_once __DIR__ . DIRECTORY_SEPARATOR . "LmsActions.php";

// SANDBOX $c = new LmsActions(true, false);
// YES FR $branches = $c->getChildNodesRecursive(1725);

function showAndUpdate($c, $branch)
{
    echo str_repeat("    ", $branch->children->depth),
    $branch->children->depth,
    " - Processing branch ",
    $branch->translation->english,
    PHP_EOL;
    if (false !== stripos($branch->translation->english, "contract ")) {
        echo str_repeat("    ", $branch->children->depth ),
        $branch->children->depth,
        " -   Renaming branch '",
        $branch->translation->english, "' to '", substr($branch->translation->english, 9),
        "... ";
        $res = $c->updateBranch($branch->id_org, substr($branch->translation->english, 9));
        if ($res->success == 1) {
            echo "ok", PHP_EOL;
        } else {
            echo "whoops!! Error!", PHP_EOL;
        }
    }
    foreach ($branch->children->children as $child) {
        showAndUpdate($c, $child);
    }
}
foreach ($branches->children as $branch) {
    showAndUpdate($lms, $branch);
}
