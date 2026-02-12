export default function VideoRenderer({ lesson }) {
    const videoUrl = lesson.video_url || ''; // Get from API
    const caption = lesson.video_caption || '';
    const settings = {
        autoplay: Boolean(lesson?.embed_settings?.autoplay),
        loop: Boolean(lesson?.embed_settings?.loop),
        muted: Boolean(lesson?.embed_settings?.muted),
        controls: lesson?.embed_settings?.controls !== false,
    };

    // Simple embed logic for YouTube
    const getEmbedUrl = (url) => {
        if (!url) return null;

        if (url.includes('youtube.com') || url.includes('youtu.be')) {
            const videoId = url.split('v=')[1]?.split('&')[0] || url.split('/').filter(Boolean).pop();
            if (!videoId) {
                return null;
            }

            const params = new URLSearchParams({
                autoplay: settings.autoplay ? '1' : '0',
                mute: settings.muted ? '1' : '0',
                controls: settings.controls ? '1' : '0',
                rel: '0',
                modestbranding: '1',
            });
            if (settings.loop) {
                params.set('loop', '1');
                params.set('playlist', videoId);
            }

            return `https://www.youtube.com/embed/${videoId}?${params.toString()}`;
        }

        if (url.includes('vimeo.com')) {
            const videoId = url.split('/').filter(Boolean).pop();
            if (!videoId) {
                return null;
            }

            const params = new URLSearchParams({
                autoplay: settings.autoplay ? '1' : '0',
                muted: settings.muted ? '1' : '0',
                loop: settings.loop ? '1' : '0',
                controls: settings.controls ? '1' : '0',
            });

            return `https://player.vimeo.com/video/${videoId}?${params.toString()}`;
        }

        return url; // Assume valid embed URL for others
    };

    const embedUrl = getEmbedUrl(videoUrl);

    if (!embedUrl) {
        return <div className="smc-error">Invalid Video URL</div>;
    }

    return (
        <div>
            <div className="smc-video-container">
                <iframe
                    width="100%"
                    height="100%"
                    src={embedUrl}
                    title={lesson.title}
                    frameBorder="0"
                    allow={settings.autoplay
                        ? 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture'
                        : 'accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture'}
                    allowFullScreen
                ></iframe>
            </div>
            {caption && <p className="mt-3 text-sm text-base-content/70">{caption}</p>}
        </div>
    );
}
