import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl, Spinner } from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

export default function Edit({ attributes, setAttributes }) {
    const { quizId } = attributes;
    const [quizzes, setQuizzes] = useState([]);
    const [isLoading, setIsLoading] = useState(true);

    useEffect(() => {
        apiFetch({ path: '/smc/v1/quizzes' })
            .then((data) => {
                setQuizzes(data.map(q => ({
                    label: q.title.rendered || __('Untitled Quiz', 'smc-viable'),
                    value: q.id
                })));
            })
            .catch(err => console.error(err))
            .finally(() => setIsLoading(false));
    }, []);

    const onChangeQuiz = (id) => {
        setAttributes({ quizId: parseInt(id) });
    };

    return (
        <div {...useBlockProps()}>
            {isLoading ? (
                <Spinner />
            ) : (
                <div style={{ padding: '20px', border: '1px dashed #ccc', textAlign: 'center' }}>
                    <h3>{__('SMC Quiz Block', 'smc-viable')}</h3>
                    <SelectControl
                        label={__('Select a Quiz to Display', 'smc-viable')}
                        value={quizId}
                        options={[
                            { label: __('Select Quiz...', 'smc-viable'), value: 0 },
                            ...quizzes
                        ]}
                        onChange={onChangeQuiz}
                    />
                </div>
            )}
            <InspectorControls>
                <PanelBody title={__('Settings', 'smc-viable')}>
                    <SelectControl
                        label={__('Select Quiz', 'smc-viable')}
                        value={quizId}
                        options={[
                            { label: __('Select Quiz...', 'smc-viable'), value: 0 },
                            ...quizzes
                        ]}
                        onChange={onChangeQuiz}
                    />
                </PanelBody>
            </InspectorControls>
        </div>
    );
}
