<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} - Green Thumb Journal</title>
    <style>
        body { margin: 0; font-family: Georgia, 'Times New Roman', serif; line-height: 1.7; color: #2b2b2b; background: #f6f3ec; }
        header { background: #3f6b3a; color: #fff; padding: 20px 16px; }
        header strong { font-size: 1.4rem; }
        main { max-width: 720px; margin: 0 auto; padding: 24px 16px 48px; }
        h1 { font-size: 2rem; line-height: 1.25; color: #2f4f2b; }
        h2 { margin-top: 2rem; color: #3f6b3a; }
        a { color: #2c6b8f; }
        nav.pages { margin: 1rem 0 2rem; padding: 12px 16px; background: #e8efdf; border-radius: 6px; }
        nav.pages a, nav.pages strong { display: inline-block; margin-right: 14px; }
        footer { text-align: center; font-size: 0.85rem; color: #666; padding: 24px 16px; border-top: 1px solid #ddd; }
    </style>
</head>
<body>
    <header><strong>Green Thumb Journal</strong> &middot; friendly houseplant tips</header>
    <main>
        <article>
            <h1>{{ $title }}</h1>
            <nav class="pages" aria-label="Pages">
                @foreach ($titles as $number => $pageTitle)
                    @if ($number === $page)
                        <strong>Page {{ $number }}</strong>
                    @else
                        <a href="{{ $number === 1 ? route('demo.advertiser') : route('demo.advertiser.page', $number) }}">Page {{ $number }}: {{ $pageTitle }}</a>
                    @endif
                @endforeach
            </nav>

            @if ($page === 1)
                <p>Every houseplant lover eventually faces the same question: is it time to repot? Roots circling the drainage holes, water running straight through the pot, or growth that has stalled for months are all friendly hints that your plant is asking for more room.</p>
                <h2>Know when to repot</h2>
                <p>Spring is usually the best season, because plants are entering their active growth phase and recover quickly. Lift the plant gently and look at the root ball. If you see more roots than soil, a bigger home will make a visible difference within a few weeks.</p>
                <p>Do not rush a plant that has just arrived from the store or one that is flowering. Give it a few weeks to settle in, and wait for the flowers to fade before you disturb the roots.</p>
                <h2>Prepare your workspace</h2>
                <p>Lay down newspaper or an old tray, and gather fresh potting mix, a slightly larger pot, a small trowel and a watering can. Water the plant a day before so the root ball slides out easily instead of crumbling.</p>
                <p>Clean tools matter more than most beginners expect. A quick rinse with warm soapy water keeps stray fungus and pests from traveling between your plants.</p>
                <h2>Loosen, trim and settle</h2>
                <p>Tip the pot sideways and ease the plant out, holding the base of the stem. Tease apart the outer roots with your fingers, and snip off any that look brown, mushy or papery. Healthy roots are firm and pale.</p>
                <p>Add a layer of fresh mix to the new pot, set the plant at the same depth as before, and fill around the sides. Press lightly, never hard, so air pockets can close without squeezing the roots.</p>
                <p>Continue with <a href="{{ route('demo.advertiser.page', 2) }}">choosing the right pot and soil</a> or skip ahead to <a href="{{ route('demo.advertiser.page', 3) }}">aftercare in the first weeks</a>.</p>
            @elseif ($page === 2)
                <p>A pot is more than a container. Its size, material and drainage decide how fast the soil dries out, and that in turn decides how often you water. Picking well saves you from most beginner mistakes.</p>
                <h2>Size: go up only a little</h2>
                <p>Choose a pot that is one or two finger widths larger than the old one, about two to five centimeters in diameter. A huge pot holds a lot of wet soil that the roots cannot use, and that is a common cause of rot.</p>
                <h2>Terracotta, plastic or glazed ceramic</h2>
                <p>Terracotta breathes and dries quickly, which suits cacti, succulents and anyone who tends to overwater. Plastic holds moisture longer and is light and cheap. Glazed ceramic looks lovely, but make sure it has a drainage hole, or use it as a decorative outer pot.</p>
                <h2>Soil that matches the plant</h2>
                <p>General potting mix works for most foliage plants, but a handful of perlite improves airflow. Succulents like a gritty mix with plenty of sand, while ferns and calatheas enjoy extra bark or coco fiber that stays evenly moist.</p>
                <p>Avoid garden soil indoors. It compacts, drains poorly and often carries pests you would rather leave outside.</p>
                <p>Back to <a href="{{ route('demo.advertiser') }}">the beginning of the guide</a>, or on to <a href="{{ route('demo.advertiser.page', 3) }}">aftercare</a>.</p>
            @else
                <p>The weeks after repotting are quiet but important. The plant is repairing tiny root damage and getting used to new soil, so a calm routine helps it far more than extra attention.</p>
                <h2>Water thoughtfully</h2>
                <p>Give the plant a thorough drink right after repotting to settle the soil, then let the top few centimeters dry before watering again. Fresh mix holds water differently from old, root-bound soil, so check with your finger rather than a calendar.</p>
                <h2>Light and location</h2>
                <p>Keep the plant in its usual spot, or somewhere with slightly softer light for a week. Direct afternoon sun on a freshly repotted plant can scorch leaves that are already under a little stress.</p>
                <h2>Hold off on fertilizer</h2>
                <p>Fresh potting mix contains enough nutrients for the first four to six weeks. Feeding too early can burn tender new roots. When you see fresh leaves unfurling, you can return to your usual diluted feeding schedule.</p>
                <h2>Do not panic about a droopy leaf</h2>
                <p>A little drooping or a yellow leaf after repotting is normal. If the plant keeps growing at the tips, it is happy. Be patient, and enjoy watching it fill its new home.</p>
                <p>Return to <a href="{{ route('demo.advertiser') }}">the start</a> or revisit <a href="{{ route('demo.advertiser.page', 2) }}">pots and soil</a>.</p>
            @endif

            <script async src="{{ url('/js/widget.js') }}" data-campaign="demo-campaign-key-0001"></script>
        </article>
    </main>
    <footer>Demo advertiser site: local testing only</footer>
</body>
</html>
