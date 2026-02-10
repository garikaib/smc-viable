export default function VideoRenderer({ lesson }) {
    const videoUrl = lesson.video_url || ''; // Get from API

    // Simple embed logic for YouTube
    const getEmbedUrl = (url) => {
        if (!url) return null;
        if (url.includes('youtube.com') || url.includes('youtu.be')) {
            const videoId = url.split('v=')[1] || url.split('/').pop();
            const cleanId = videoId ? videoId.split('&')[0] : null;
            return cleanId ? `https://www.youtube.com/embed/${cleanId}` : null;
        }
        return url; // Assume valid embed URL for others
    };

    const embedUrl = getEmbedUrl(videoUrl);

    if (!embedUrl) {
        return <div className="smc-error">Invalid Video URL</div>;
    }

    return (
        <div className="smc-video-container">
            <iframe
                width="100%"
                height="100%"
                src={embedUrl}
                title={lesson.title}
                frameBorder="0"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                allowFullScreen
            ></iframe>
        </div>
    );
}
