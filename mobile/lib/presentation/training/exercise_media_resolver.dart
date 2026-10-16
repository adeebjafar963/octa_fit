/// Maps a bodybuilding exercise slug to its local looping animation.
class ExerciseMediaResolver {
  const ExerciseMediaResolver._();

  static const groups = <String>[
    'full-body',
    'hamstrings',
    'shoulders',
    'forearms',
    'triceps',
    'biceps',
    'glutes',
    'calves',
    'chest',
    'back',
    'quads',
    'core',
  ];

  static String? assetForSlug(String slug) {
    if (!slug.startsWith('bb-')) return null;
    final rest = slug.substring(3);
    for (final group in groups) {
      if (rest == group || rest.startsWith('$group-')) {
        final folder = group.replaceAll('-', '_');
        final file = slug.replaceAll('-', '_');
        return 'assets/exercises/bodybuilding/$folder/$file.gif';
      }
    }
    return null;
  }
}
