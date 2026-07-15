import yaml

"""
update docker-compose.yml to specify caching strategy for github CI
"""

with open("docker-compose.yml", "r") as f:
    data = yaml.safe_load(f)
for service_name, service_data in data["services"].items():
    if "build" not in service_data:
        continue
    old_build = service_data["build"]
    assert isinstance(old_build, str), (
        f"expected string (example: 'web', 'sql', 'identity'), found: {old_build}"
    )
    new_build = dict(
        context=old_build,
        cache_from=[
            r"type=registry,ref=ghcr.io/${REPO_OWNER}/unity-dev-%s:buildcache" % (old_build)
        ],
        cache_to=[
            r"type=registry,ref=ghcr.io/${REPO_OWNER}/unity-dev-%s:buildcache,mode=max"
            % (old_build)
        ],
    )
    data["services"][service_name]["build"] = new_build

print(yaml.safe_dump(data, sort_keys=False))
