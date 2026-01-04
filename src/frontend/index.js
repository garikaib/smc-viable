/**
 * Frontend Entry Point
 */
import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';

registerBlockType('smc/quiz', {
    edit: () => {
        const blockProps = useBlockProps();
        return <div {...blockProps}>SMC Quiz Block Placeholder</div>;
    },
    save: () => null,
});
