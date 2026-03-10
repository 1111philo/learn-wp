(function (wp) {
	'use strict';

	if (!wp || !wp.plugins || !wp.editPost || !wp.components || !wp.element) {
		return;
	}

	var el = wp.element.createElement;
	var PluginSidebar = wp.editPost.PluginSidebar;
	var PanelBody = wp.components.PanelBody;
	var TextareaControl = wp.components.TextareaControl;
	var Button = wp.components.Button;
	var Notice = wp.components.Notice;

	var LearnSidebar = function () {
		var feedback = '';

		return el(
			PluginSidebar,
			{
				name: 'learn-feedback-sidebar',
				title: 'Learn Feedback'
			},
			el(
				PanelBody,
				{ title: 'AI Generated Content', initialOpen: true },
				el(Notice, { status: 'info', isDismissible: false }, 'This content is agent-authored and locked. Provide feedback to regenerate.'),
				el(TextareaControl, {
					label: 'Feedback',
					help: 'Describe what should improve. The agent will regenerate content from feedback.',
					onChange: function (value) {
						feedback = value;
					}
				}),
				el(Button, { variant: 'primary', disabled: true }, 'Regeneration Endpoint Pending')
			)
		);
	};

	wp.plugins.registerPlugin('learn-editor-sidebar', {
		render: LearnSidebar,
		icon: 'welcome-learn-more'
	});
})(window.wp);
