<?php
declare(strict_types=1);

namespace WyriHaximus\TwigView\Lib;

/**
 * Class TreeScanner.
 */
final class TreeScanner
{
    /**
     * Return all sections (app & plugins) with an Template directory.
     *
     * @return array
     */
    public static function all(): array
    {
        return static::deepen(RelativeScanner::all());
    }

    /**
     * Return all templates for a given plugin.
     *
     * @param string $plugin The plugin to find all templates for.
     * @return array
     */
    public static function plugin(string $plugin): array
    {
        return static::deepen([
            $plugin => RelativeScanner::plugin($plugin),
        ])[$plugin];
    }

    /**
     * Strip the absolute path of template's paths for all given sections.
     *
     * @param array $sections Sections to iterate over.
     * @return array
     */
    protected static function deepen(array $sections): array
    {
        foreach ($sections as $section => $paths) {
            $sections[$section] = static::convertToTree($paths);
        }

        return $sections;
    }

    /**
     * Turn a set of paths into a tree.
     *
     * @param array $paths Paths to turn into a tree.
     * @return array
     */
    protected static function convertToTree(array $paths): array
    {
        foreach ($paths as $index => $path) {
            static::convertPathToTree($paths, $index, $path);
        }

        return $paths;
    }

    /**
     * Convert a path into a tree when it contains a directory separator.
     *
     * @param array $paths The paths to work on.
     * @param mixed $index Index of $path.
     * @param string $path Path to breakup and turn into a tree.
     * @return void
     */
    protected static function convertPathToTree(array &$paths, $index, string $path)
    {
        if (strpos($path, DIRECTORY_SEPARATOR) !== false) {
            $chunks = explode(DIRECTORY_SEPARATOR, $path);
            $paths = static::branch($paths, $chunks);
            unset($paths[$index]);
        }
    }

    /**
     * Create a branch for the current level and push a twig on it.
     *
     * @param array $paths Paths to append.
     * @param string[] $branches Branches to use until only one left.
     * @return array
     */
    protected static function branch(array $paths, array $branches): array
    {
        $twig = array_shift($branches);
        if (count($branches) === 0) {
            $paths[] = $twig;

            return $paths;
        }

        if (!isset($paths[$twig])) {
            $paths[$twig] = [];
        }

        $paths[$twig] = static::branch($paths[$twig], $branches);

        return $paths;
    }
}
