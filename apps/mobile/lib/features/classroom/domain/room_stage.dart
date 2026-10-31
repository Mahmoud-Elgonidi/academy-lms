/// What the lesson room shows where, worked out from plain data about the
/// people in it (no LiveKit here, so it is tested on its own):
/// - someone shares a screen: the screen large and whole ([TileFit.contain]),
///   the presenter's camera small on it, the other cameras in a strip;
/// - one other person: them large, me small and floating;
/// - two or more others: everyone in a grid;
/// - a tile tapped: pinned large (spotlight), the rest in the strip.
library;

/// Where a tile's picture comes from.
enum FeedSource { camera, screen }

/// How a tile fills its box: a camera fills it (cropped), a shared screen
/// shows whole (nothing of it may be cut off).
enum TileFit { cover, contain }

/// The layout asked for from the room's "layout" choice.
enum RoomLayout { auto, grid, spotlight }

/// How the stage is laid out this moment.
enum StageKind {
  /// Nobody else yet: me large while I wait.
  alone,

  /// One other person: them large, me floating.
  pair,

  /// One tile large (a screen, a pinned tile, or the spotlight layout), the rest in a strip.
  spotlight,

  /// Everyone the same size.
  grid,
}

/// One person in the room, as the stage needs them.
class RoomPerson {
  const RoomPerson({
    required this.identity,
    required this.name,
    this.isLocal = false,
    this.camera = false,
    this.screen = false,
    this.micOn = false,
    this.speaking = false,
  });

  final String identity;
  final String name;
  final bool isLocal;

  /// A camera picture to show now (published, received and not muted).
  final bool camera;

  /// A shared screen to show now.
  final bool screen;
  final bool micOn;
  final bool speaking;
}

/// One box on the stage: a person's camera (their initial when it is off) or their screen.
class Tile {
  const Tile(this.person, this.source);

  final RoomPerson person;
  final FeedSource source;

  /// Stable across rebuilds: what a pin holds on to.
  String get key => '${person.identity}:${source.name}';

  TileFit get fit => source == FeedSource.screen ? TileFit.contain : TileFit.cover;

  /// A picture to draw (else the person's initial).
  bool get hasVideo => source == FeedSource.screen ? person.screen : person.camera;

  @override
  bool operator ==(Object other) => other is Tile && other.key == key;

  @override
  int get hashCode => key.hashCode;

  @override
  String toString() => 'Tile($key)';
}

/// The stage worked out by [pickStage].
class StagePlan {
  const StagePlan({required this.kind, this.main, this.overlay, this.self, this.strip = const [], this.grid = const [], this.pinned});

  final StageKind kind;

  /// The large tile (alone, pair, spotlight).
  final Tile? main;

  /// The presenter's camera small over their shared screen.
  final Tile? overlay;

  /// My own camera, floating over the stage (draggable).
  final Tile? self;

  /// The small tiles beside the large one.
  final List<Tile> strip;

  /// Everyone, the same size ([StageKind.grid]).
  final List<Tile> grid;

  /// The pin still in force (a pin on someone who left falls away).
  final String? pinned;

  /// Every tile on the stage, the large one first.
  List<Tile> get tiles => [?main, ?overlay, ...strip, ...grid, ?self];
}

/// Lays out the stage for [people] (in the order they came in), [layout]
/// as chosen, and the tile [pinned] by a tap.
StagePlan pickStage(List<RoomPerson> people, {RoomLayout layout = RoomLayout.auto, String? pinned}) {
  final me = people.where((p) => p.isLocal).firstOrNull;
  final others = people.where((p) => !p.isLocal).toList();
  // Cameras: the others first, me last; screens: the others' before mine.
  final cameras = [for (final p in others) Tile(p, FeedSource.camera), if (me != null) Tile(me, FeedSource.camera)];
  final screens = [
    for (final p in others)
      if (p.screen) Tile(p, FeedSource.screen),
    if (me != null && me.screen) Tile(me, FeedSource.screen),
  ];
  final all = [...screens, ...cameras];
  final pin = all.where((t) => t.key == pinned).firstOrNull;

  if (all.isEmpty) return const StagePlan(kind: StageKind.alone);

  if (layout == RoomLayout.grid && pin == null) {
    return StagePlan(kind: StageKind.grid, grid: all);
  }

  // One tile large: the pinned one, else a shared screen, else (spotlight asked for) the first other person.
  final Tile? large = pin ?? screens.firstOrNull ?? (layout == RoomLayout.spotlight && others.isNotEmpty ? cameras.first : null);
  if (large != null) {
    // A screen large keeps its presenter's face on it.
    final overlay = large.source == FeedSource.screen ? Tile(large.person, FeedSource.camera) : null;
    final rest = all.where((t) => t != large && t != overlay).toList();
    final mine = me == null ? null : Tile(me, FeedSource.camera);
    // Only my own camera left: it floats, as in a call of two.
    final alone = rest.length == 1 && rest.single == mine && large != mine;
    return StagePlan(
      kind: StageKind.spotlight,
      main: large,
      overlay: overlay,
      strip: alone ? const [] : rest,
      self: alone ? mine : null,
      pinned: pin?.key,
    );
  }

  if (others.isEmpty) return StagePlan(kind: StageKind.alone, main: cameras.single);
  if (others.length == 1) {
    return StagePlan(kind: StageKind.pair, main: cameras.first, self: me == null ? null : Tile(me, FeedSource.camera));
  }
  return StagePlan(kind: StageKind.grid, grid: cameras);
}

/// A name as a tile says it in a group lesson's room: the first name only
/// (families see their classmates by first name), a title such as "أ." or
/// "Dr." kept with the name after it.
String firstNameOf(String name) {
  final words = name.trim().split(RegExp(r'\s+')).where((w) => w.isNotEmpty).toList();
  if (words.isEmpty) return name.trim();
  final title = words.first.endsWith('.') && words.length > 1;
  return title ? '${words[0]} ${words[1]}' : words.first;
}

/// The names the room's tiles carry: in a group lesson's room (two or more
/// others) everyone by their first name, else as the room gave them.
List<RoomPerson> namedForRoom(List<RoomPerson> people) {
  final group = people.where((p) => !p.isLocal).length > 1;
  if (!group) return people;
  return [
    for (final p in people)
      RoomPerson(
        identity: p.identity,
        name: firstNameOf(p.name),
        isLocal: p.isLocal,
        camera: p.camera,
        screen: p.screen,
        micOn: p.micOn,
        speaking: p.speaking,
      ),
  ];
}

/// The next choice of the layout button: auto, grid, spotlight, and round again.
RoomLayout nextLayout(RoomLayout layout) => RoomLayout.values[(layout.index + 1) % RoomLayout.values.length];

/// How a call sounds and looks from here, from the worst line in the room.
enum RoomQuality { good, fair, poor, unknown }

/// mm:ss (h:mm:ss past an hour), digits as typed.
String roomClock(Duration d) {
  String two(int n) => n.toString().padLeft(2, '0');
  final s = two(d.inSeconds % 60);
  return d.inHours > 0 ? '${d.inHours}:${two(d.inMinutes % 60)}:$s' : '${two(d.inMinutes)}:$s';
}

/// How many columns give [n] tiles the most room in a box of [width] x
/// [height], each tile about 4:3 (a phone held upright gets about 3:4).
int gridColumns(int n, double width, double height) {
  if (n <= 1 || width <= 0 || height <= 0) return 1;
  final aspect = width >= height ? 4 / 3 : 3 / 4;
  var best = 1;
  var bestArea = 0.0;
  for (var cols = 1; cols <= n; cols++) {
    final rows = (n + cols - 1) ~/ cols;
    final cellW = width / cols;
    final cellH = height / rows;
    // The largest tile of that shape fitting the cell.
    final w = cellW / cellH > aspect ? cellH * aspect : cellW;
    final area = w * (w / aspect);
    if (area > bestArea + 0.5) {
      bestArea = area;
      best = cols;
    }
  }
  return best;
}
