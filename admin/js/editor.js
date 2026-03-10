(function (wp) {
    'use strict';

    var el = wp.element.createElement;
    var registerPlugin = wp.plugins.registerPlugin;
    var PluginSidebar = wp.editPost.PluginSidebar;
    var PluginSidebarMoreMenuItem = wp.editPost.PluginSidebarMoreMenuItem;
    var PanelBody = wp.components.PanelBody;
    var TextareaControl = wp.components.TextareaControl;
    var Button = wp.components.Button;
    var withSelect = wp.data.withSelect;
    var withDispatch = wp.data.withDispatch;
    var compose = wp.compose.compose;

    var FeedbackSidebar = function () {
        var [feedback, setFeedback] = wp.element.useState('');

        return el(
            wp.element.Fragment,
            {},
            el(
                PluginSidebarMoreMenuItem,
                {
                    target: 'learn-sidebar',
                    icon: 'welcome-learn-more'
                },
                learnEditor.labels.sidebar_title
            ),
            el(
                PluginSidebar,
                {
                    name: 'learn-sidebar',
                    title: learnEditor.labels.sidebar_title,
                    icon: 'welcome-learn-more'
                },
                el(
                    PanelBody,
                    {
                        title: learnEditor.labels.feedback_label,
                        initialOpen: true
                    },
                    el(
                        TextareaControl,
                        {
                            label: learnEditor.labels.feedback_label,
                            help: learnEditor.labels.feedback_help,
                            value: feedback,
                            onChange: function (value) {
                                setFeedback(value);
                            }
                        }
                    ),
                    el(
                        Button,
                        {
                            isPrimary: true,
                            onClick: function () {
                                if (!feedback) return;

                                wp.ajax.post('1111_submit_feedback', {
                                    post_id: learnEditor.post_id,
                                    feedback: feedback,
                                    nonce: learnEditor.nonce
                                }).done(function (response) {
                                    alert(response.message);
                                    setFeedback('');
                                }).fail(function (err) {
                                    alert(err.message || 'Error submitting feedback');
                                });
                            }
                        },
                        learnEditor.labels.submit_feedback
                    )
                ),
                learnEditor.activity_data ? el(
                    PanelBody,
                    {
                        title: 'Activity Details',
                        initialOpen: false
                    },
                    el('p', {}, el('strong', {}, 'Type: '), learnEditor.activity_data.activity_type),
                    el('p', {}, el('strong', {}, 'XP: '), learnEditor.activity_data.xp_value),
                    el('p', {}, el('strong', {}, 'Prompt: '), learnEditor.activity_data.prompt)
                ) : null
            )
        );
    };

    registerPlugin('learn-sidebar', {
        render: FeedbackSidebar
    });

})(window.wp);
