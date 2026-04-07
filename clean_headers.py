import os, glob, re

directories = [
    'templates/avis',
    'templates/reclamation',
    'templates/home',
]

for d in directories:
    for filepath in glob.glob(d + '/**/*.html.twig', recursive=True):
        if 'base_front' not in filepath and 'header.html.twig' not in filepath:
            with open(filepath, 'r', encoding='utf-8') as f:
                content = f.read()
            
            # Check if it extends base_front.html.twig
            if '{% extends \'base_front.html.twig\' %}' in content or '{% extends "base_front.html.twig" %}' in content:
                # Remove {% include 'home/partials/header.html.twig' %}
                new_content = re.sub(r'\{%\s*include\s+[\'"].*header.*[\'"]\s*%\}', '', content)
                # Remove {% include 'home/partials/footer.html.twig' %}
                new_content = re.sub(r'\{%\s*include\s+[\'"].*footer.*[\'"]\s*%\}', '', new_content)
                
                if new_content != content:
                    with open(filepath, 'w', encoding='utf-8') as f:
                        f.write(new_content)
                    print(f'Cleaned references in {filepath}')
