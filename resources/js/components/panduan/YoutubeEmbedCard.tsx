interface YoutubeEmbedCardProps {
    videoId: string;
    title: string;
    description?: string;
}

export default function YoutubeEmbedCard({ videoId, title, description }: YoutubeEmbedCardProps) {
    return (
        <article className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xs transition-shadow hover:shadow-md dark:border-slate-800 dark:bg-slate-900">
            <div className="aspect-video w-full bg-slate-950">
                <iframe
                    src={`https://www.youtube-nocookie.com/embed/${videoId}`}
                    title={title}
                    loading="lazy"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                    allowFullScreen
                    className="h-full w-full"
                />
            </div>
            <div className="flex flex-col gap-1 p-4">
                <h3 className="text-sm font-bold text-slate-900 dark:text-white">{title}</h3>
                {description && <p className="text-xs leading-relaxed text-slate-500 dark:text-slate-400">{description}</p>}
            </div>
        </article>
    );
}
