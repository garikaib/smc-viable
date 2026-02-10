export default function TextRenderer({ lesson }) {
    return (
        <div className="smc-text-lesson">
            <div
                className="smc-lesson-content"
                dangerouslySetInnerHTML={{ __html: lesson.content }} // Content from WP blocks
            />
        </div>
    );
}
