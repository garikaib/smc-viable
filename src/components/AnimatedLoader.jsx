import { useEffect, useRef } from '@wordpress/element';
import gsap from 'gsap';
import './animated-loader.scss';

export default function AnimatedLoader({
    message = 'Loading...',
    className = '',
    tone = 'teal',
    compact = false,
}) {
    const rootRef = useRef(null);

    useEffect(() => {
        if (!rootRef.current) {
            return undefined;
        }

        const prefersReducedMotion =
            typeof window !== 'undefined' &&
            typeof window.matchMedia === 'function' &&
            window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        if (prefersReducedMotion) {
            return undefined;
        }

        const ctx = gsap.context(() => {
            gsap.fromTo(
                '.smc-animated-loader__dot',
                { y: 0, scale: 0.92, opacity: 0.65 },
                {
                    y: -8,
                    scale: 1,
                    opacity: 1,
                    duration: 0.65,
                    repeat: -1,
                    yoyo: true,
                    ease: 'sine.inOut',
                    stagger: 0.12,
                }
            );

            gsap.fromTo(
                '.smc-animated-loader__ring',
                { rotate: 0, scale: 0.98, opacity: 0.65 },
                {
                    rotate: 360,
                    scale: 1.04,
                    opacity: 1,
                    duration: 2.2,
                    repeat: -1,
                    ease: 'none',
                }
            );

            gsap.fromTo(
                '.smc-animated-loader__message',
                { opacity: 0.6 },
                {
                    opacity: 1,
                    duration: 1,
                    repeat: -1,
                    yoyo: true,
                    ease: 'sine.inOut',
                }
            );
        }, rootRef);

        return () => ctx.revert();
    }, []);

    const classes = [
        'smc-animated-loader',
        compact ? 'is-compact' : '',
        tone === 'red' ? 'is-red' : '',
        tone === 'gold' ? 'is-gold' : '',
        className,
    ]
        .filter(Boolean)
        .join(' ');

    return (
        <div className={classes} ref={rootRef} role="status" aria-live="polite">
            <div className="smc-animated-loader__visual" aria-hidden="true">
                <div className="smc-animated-loader__ring" />
                <div className="smc-animated-loader__dots">
                    <span className="smc-animated-loader__dot" />
                    <span className="smc-animated-loader__dot" />
                    <span className="smc-animated-loader__dot" />
                </div>
            </div>
            <div className="smc-animated-loader__message">{message}</div>
        </div>
    );
}
